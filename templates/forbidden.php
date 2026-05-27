<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="v24-smh-auth-shell">
    <section class="v24-smh-auth-card">
        <div class="v24-smh-auth-brand">
            <span class="v24-smh-auth-eyebrow">Valore 24 AI Office</span>
            <div class="v24-smh-brand-block" aria-label="Valore 24 AI Office">
                <strong class="v24-smh-brand-mark">VALORE<span>24</span><em>AI</em></strong>
                <span class="v24-smh-brand-submark">Office</span>
            </div>
            <h1>Accesso non autorizzato</h1>
            <p>Il tuo account WordPress e autenticato, ma non dispone dei permessi per usare SmartMail Hub.</p>
        </div>
        <div class="v24-smh-auth-meta">
            <a href="<?php echo esc_url(wp_logout_url(get_permalink() ?: home_url('/'))); ?>">Esci e cambia account</a>
        </div>
    </section>
</div>
