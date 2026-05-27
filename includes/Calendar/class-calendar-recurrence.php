<?php
if (!defined('ABSPATH')) {
    exit;
}

class V24_SMH_Calendar_Recurrence
{
    private const WEEKDAY_MAP = [
        'MO' => 1,
        'TU' => 2,
        'WE' => 3,
        'TH' => 4,
        'FR' => 5,
        'SA' => 6,
        'SU' => 7,
    ];

    public function normalize(array $input, string $start_at, string $timezone)
    {
        $frequency = sanitize_key($input['frequency'] ?? 'none');
        if ($frequency === '' || $frequency === 'none') {
            return null;
        }

        if (!in_array($frequency, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            return new WP_Error('validation_error', 'Frequenza ricorrenza non valida.', ['status' => 400]);
        }

        $start = $this->create_datetime($start_at, $timezone);
        if (!$start) {
            return new WP_Error('validation_error', 'Data iniziale ricorrenza non valida.', ['status' => 400]);
        }

        $interval = max(1, min(365, (int) ($input['interval'] ?? 1)));
        $weekdays = array_values(array_unique(array_filter(array_map(function ($day) {
            $day = strtoupper(sanitize_text_field((string) $day));
            return isset(self::WEEKDAY_MAP[$day]) ? $day : '';
        }, (array) ($input['weekdays'] ?? [])))));

        if ($frequency === 'weekly' && !$weekdays) {
            $weekdays = [$this->weekday_code((int) $start->format('N'))];
        }

        $monthly_mode = sanitize_key($input['monthly_mode'] ?? 'day_of_month');
        if (!in_array($monthly_mode, ['day_of_month', 'nth_weekday'], true)) {
            $monthly_mode = 'day_of_month';
        }

        $month_day = max(1, min(31, (int) ($input['month_day'] ?? (int) $start->format('j'))));
        $nth_week = (int) ($input['nth_week'] ?? 1);
        if (!in_array($nth_week, [-1, 1, 2, 3, 4], true)) {
            $nth_week = 1;
        }

        $nth_weekday = strtoupper(sanitize_text_field((string) ($input['nth_weekday'] ?? $this->weekday_code((int) $start->format('N')))));
        if (!isset(self::WEEKDAY_MAP[$nth_weekday])) {
            $nth_weekday = $this->weekday_code((int) $start->format('N'));
        }

        $months = array_values(array_unique(array_filter(array_map(function ($month) {
            $month = (int) $month;
            return $month >= 1 && $month <= 12 ? $month : 0;
        }, (array) ($input['months'] ?? [])))));
        if ($frequency === 'yearly' && !$months) {
            $months = [(int) $start->format('n')];
        }

        $until = sanitize_text_field($input['until'] ?? '');
        if ($until !== '') {
            $until_dt = $this->create_datetime($until, $timezone);
            if (!$until_dt || $until_dt < $start) {
                return new WP_Error('validation_error', 'Data fine ricorrenza non valida.', ['status' => 400]);
            }
            $until = $this->to_mysql($until_dt);
        } else {
            $until = null;
        }

        $count = isset($input['count']) && $input['count'] !== '' ? max(1, min(999, (int) $input['count'])) : null;
        $exceptions = array_values(array_unique(array_filter(array_map(function ($value) use ($timezone) {
            $dt = $this->create_datetime((string) $value, $timezone);
            return $dt ? $this->to_mysql($dt) : null;
        }, (array) ($input['exceptions'] ?? [])))));

        return [
            'frequency' => $frequency,
            'interval' => $interval,
            'weekdays' => $weekdays,
            'monthly_mode' => $monthly_mode,
            'month_day' => $month_day,
            'nth_week' => $nth_week,
            'nth_weekday' => $nth_weekday,
            'months' => $months,
            'until' => $until,
            'count' => $count,
            'exceptions' => $exceptions,
        ];
    }

    public function decode($value): ?array
    {
        if (!$value) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function expand(array $event, string $range_start, string $range_end): array
    {
        $timezone = sanitize_text_field($event['timezone'] ?? wp_timezone_string());
        $series_start = $this->create_datetime((string) ($event['start_at'] ?? ''), $timezone);
        $series_end = $this->create_datetime((string) ($event['end_at'] ?? ''), $timezone);
        $window_start = $this->create_datetime($range_start ?: '1970-01-01 00:00:00', $timezone);
        $window_end = $this->create_datetime($range_end ?: '2999-12-31 23:59:59', $timezone);

        if (!$series_start || !$series_end || !$window_start || !$window_end) {
            return [];
        }

        $recurrence = $this->decode($event['recurrence_json'] ?? null);
        if (!$recurrence) {
            return $this->overlaps($series_start, $series_end, $window_start, $window_end) ? [[
                'occurrence_start' => $this->to_mysql($series_start),
                'occurrence_end' => $this->to_mysql($series_end),
            ]] : [];
        }

        $duration = $series_end->getTimestamp() - $series_start->getTimestamp();
        $until = !empty($recurrence['until']) ? $this->create_datetime((string) $recurrence['until'], $timezone) : null;
        $max_count = !empty($recurrence['count']) ? (int) $recurrence['count'] : null;
        $matches = [];
        $occurrence_count = 0;
        $safety = 0;

        foreach ($this->candidate_starts($series_start, $recurrence) as $candidate_start) {
            $safety++;
            if ($safety > 1000) {
                break;
            }

            if ($until && $candidate_start > $until) {
                break;
            }

            $occurrence_count++;
            if ($max_count !== null && $occurrence_count > $max_count) {
                break;
            }

            $candidate_end = $candidate_start->modify('+' . $duration . ' seconds');
            if ($candidate_end < $window_start) {
                continue;
            }

            if ($candidate_start > $window_end) {
                break;
            }

            $matches[] = [
                'occurrence_start' => $this->to_mysql($candidate_start),
                'occurrence_end' => $this->to_mysql($candidate_end),
            ];
        }

        return $matches;
    }

    private function candidate_starts(DateTimeImmutable $series_start, array $recurrence): Generator
    {
        $frequency = sanitize_key($recurrence['frequency'] ?? 'daily');
        $interval = max(1, (int) ($recurrence['interval'] ?? 1));

        if ($frequency === 'daily') {
            $current = $series_start;
            while (true) {
                yield $current;
                $current = $current->modify('+' . $interval . ' days');
            }
        }

        if ($frequency === 'weekly') {
            $weekdays = array_values(array_unique(array_filter(array_map(function ($day) {
                $day = strtoupper((string) $day);
                return isset(self::WEEKDAY_MAP[$day]) ? self::WEEKDAY_MAP[$day] : 0;
            }, (array) ($recurrence['weekdays'] ?? [])))));
            sort($weekdays);

            if (!$weekdays) {
                $weekdays = [(int) $series_start->format('N')];
            }

            $week_start = $series_start->modify('monday this week');
            $week_index = 0;

            while (true) {
                $base_week = $week_start->modify('+' . ($week_index * $interval) . ' weeks');
                foreach ($weekdays as $weekday) {
                    $candidate = $base_week
                        ->modify('+' . ($weekday - 1) . ' days')
                        ->setTime(
                            (int) $series_start->format('H'),
                            (int) $series_start->format('i'),
                            (int) $series_start->format('s')
                        );

                    if ($candidate < $series_start) {
                        continue;
                    }

                    yield $candidate;
                }
                $week_index++;
            }
        }

        if ($frequency === 'monthly') {
            $mode = sanitize_key($recurrence['monthly_mode'] ?? 'day_of_month');
            $month_index = 0;

            while (true) {
                $month_anchor = $series_start->modify('first day of +' . ($month_index * $interval) . ' months');
                $candidate = $mode === 'nth_weekday'
                    ? $this->nth_weekday_of_month(
                        (int) $month_anchor->format('Y'),
                        (int) $month_anchor->format('n'),
                        (int) ($recurrence['nth_week'] ?? 1),
                        strtoupper((string) ($recurrence['nth_weekday'] ?? $this->weekday_code((int) $series_start->format('N')))),
                        $series_start
                    )
                    : $this->month_day_occurrence(
                        (int) $month_anchor->format('Y'),
                        (int) $month_anchor->format('n'),
                        (int) ($recurrence['month_day'] ?? (int) $series_start->format('j')),
                        $series_start
                    );

                if ($candidate >= $series_start) {
                    yield $candidate;
                }

                $month_index++;
            }
        }

        if ($frequency === 'yearly') {
            $mode = sanitize_key($recurrence['monthly_mode'] ?? 'day_of_month');
            $months = (array) ($recurrence['months'] ?? [(int) $series_start->format('n')]);
            $months = array_values(array_unique(array_filter(array_map('intval', $months))));
            sort($months);
            $year_index = 0;

            while (true) {
                $year = (int) $series_start->format('Y') + ($year_index * $interval);
                foreach ($months as $month) {
                    $candidate = $mode === 'nth_weekday'
                        ? $this->nth_weekday_of_month(
                            $year,
                            $month,
                            (int) ($recurrence['nth_week'] ?? 1),
                            strtoupper((string) ($recurrence['nth_weekday'] ?? $this->weekday_code((int) $series_start->format('N')))),
                            $series_start
                        )
                        : $this->month_day_occurrence(
                            $year,
                            $month,
                            (int) ($recurrence['month_day'] ?? (int) $series_start->format('j')),
                            $series_start
                        );

                    if ($candidate < $series_start) {
                        continue;
                    }

                    yield $candidate;
                }
                $year_index++;
            }
        }
    }

    private function month_day_occurrence(int $year, int $month, int $day, DateTimeImmutable $series_start): DateTimeImmutable
    {
        $last_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $target_day = min(max(1, $day), $last_day);

        return $series_start->setDate($year, $month, $target_day);
    }

    private function nth_weekday_of_month(int $year, int $month, int $nth_week, string $weekday_code, DateTimeImmutable $series_start): DateTimeImmutable
    {
        $weekday = self::WEEKDAY_MAP[$weekday_code] ?? (int) $series_start->format('N');
        $cursor = $series_start->setDate($year, $month, 1);

        if ($nth_week === -1) {
            $cursor = $series_start->setDate($year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));
            while ((int) $cursor->format('N') !== $weekday) {
                $cursor = $cursor->modify('-1 day');
            }
            return $cursor;
        }

        while ((int) $cursor->format('N') !== $weekday) {
            $cursor = $cursor->modify('+1 day');
        }

        return $cursor->modify('+' . (($nth_week - 1) * 7) . ' days');
    }

    private function overlaps(DateTimeImmutable $start, DateTimeImmutable $end, DateTimeImmutable $window_start, DateTimeImmutable $window_end): bool
    {
        return $start <= $window_end && $end >= $window_start;
    }

    private function create_datetime(string $value, string $timezone): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable(str_replace('T', ' ', $value), new DateTimeZone($timezone ?: 'UTC'));
        } catch (Exception $exception) {
            return null;
        }
    }

    private function to_mysql(DateTimeImmutable $datetime): string
    {
        return $datetime->format('Y-m-d H:i:s');
    }

    private function weekday_code(int $weekday): string
    {
        foreach (self::WEEKDAY_MAP as $code => $value) {
            if ($value === $weekday) {
                return $code;
            }
        }

        return 'MO';
    }
}
