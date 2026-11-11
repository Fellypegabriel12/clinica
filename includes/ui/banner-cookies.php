<?php
declare(strict_types=1);
if (consentimento_cookies() === null):
?>
<div id="banner-cookies" class="banner-cookies" role="region" aria-label="Consentimento de cookies">
    <p>
        Usamos cookies essenciais para o site funcionar (login, marcações).
        Com a tua autorização, podemos também usar cookies analíticos para melhorar o serviço.
        <a href="cookies.php">Saber mais</a>.
    </p>
    <div class="banner-cookies-acoes">
        <form method="post" action="guardar-cookies.php" class="form-inline">
            <?= csrf_campo() ?>
            <input type="hidden" name="analiticos" value="0">
            <button type="submit" class="botao botao-secundario">Só essenciais</button>
        </form>
        <form method="post" action="guardar-cookies.php" class="form-inline">
            <?= csrf_campo() ?>
            <input type="hidden" name="analiticos" value="1">
            <button type="submit" class="botao">Aceitar todos</button>
        </form>
    </div>
</div>
<script>
(function () {
    var b = document.getElementById('banner-cookies');
    if (!b) { return; }
    try {
        if (document.cookie.indexOf('consentimento_cookies=') !== -1) { b.remove(); }
    } catch (e) {}
})();
</script>
<?php endif; ?>