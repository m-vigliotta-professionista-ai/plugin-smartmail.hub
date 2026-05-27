<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_url = get_permalink() ?: home_url('/');
$login_error = isset($login_error) ? wp_kses_post($login_error) : '';
?>
<div class="v24-smh-auth-shell">
    <section class="v24-smh-auth-card">
        <div class="v24-smh-auth-brand">
            <div class="v24-smh-brand-block" aria-label="Valore 24 AI Office">
                <strong class="v24-smh-brand-mark">VALORE<span>24</span><em>AI</em></strong>
                <span class="v24-smh-brand-submark">Office</span>
            </div>
            <h1>SmartMail Hub</h1>
        </div>

        <?php if ($login_error) : ?>
            <div class="v24-smh-auth-alert" role="alert"><?php echo $login_error; ?></div>
        <?php endif; ?>

        <form method="post" class="v24-smh-auth-form">
            <?php wp_nonce_field('v24_smh_frontend_login', 'v24_smh_login_nonce'); ?>
            <input type="hidden" name="v24_smh_frontend_login" value="1">

            <label>
                <span>Utente o email</span>
                <input type="text" name="log" autocomplete="username" required>
            </label>

            <label>
                <span>Password</span>
                <input type="password" name="pwd" autocomplete="current-password" required>
            </label>

            <label class="v24-smh-checkbox v24-smh-auth-remember">
                <input type="checkbox" name="rememberme" value="forever">
                <span>Ricordami su questo dispositivo</span>
            </label>

            <button type="submit" class="v24-smh-button-primary">Entra in SmartMail Hub</button>
        </form>

        <div class="v24-smh-auth-meta">
            <a href="<?php echo esc_url(wp_lostpassword_url($current_url)); ?>">Password dimenticata?</a>
        </div>
    </section>
</div>
