<?php
require_once __DIR__ . '/../core/conteudo.php';
$__pdoRod = function_exists('db') ? db() : null;
$__cnome = $__pdoRod ? clinica_dado($__pdoRod, 'clinica_nome', CLINICA_NOME) : CLINICA_NOME;
$__cnif = $__pdoRod ? clinica_dado($__pdoRod, 'clinica_nif', CLINICA_NIF) : CLINICA_NIF;
$__cers = $__pdoRod ? clinica_dado($__pdoRod, 'clinica_ers', CLINICA_ERS) : CLINICA_ERS;
$__cmorada = $__pdoRod ? clinica_dado($__pdoRod, 'clinica_morada', CLINICA_MORADA) : CLINICA_MORADA;
$__ctel = $__pdoRod ? clinica_dado($__pdoRod, 'contacto_telefone', CLINICA_TELEFONE) : CLINICA_TELEFONE;
$__cemail = $__pdoRod ? clinica_dado($__pdoRod, 'contacto_email', CLINICA_EMAIL) : CLINICA_EMAIL;
$__cdir = $__pdoRod ? clinica_dado($__pdoRod, 'clinica_diretor', CLINICA_DIRETOR) : CLINICA_DIRETOR;
$__comd = $__pdoRod ? clinica_dado($__pdoRod, 'clinica_diretor_omd', CLINICA_DIRETOR_OMD) : CLINICA_DIRETOR_OMD;
?>
</main>
<footer class="rodape">
    <div class="rodape-conteudo">
        <div class="rodape-coluna">
            <p><strong><?= e($__cnome) ?></strong></p>
            <p>NIF: <?= e($__cnif) ?></p>
            <p>Registo ERS n.º <?= e($__cers) ?></p>
            <p><?= e($__cmorada) ?></p>
            <p>Tel.: <?= e($__ctel) ?> · <?= e($__cemail) ?></p>
            <p>Diretor clínico: <?= e($__cdir) ?> (<?= e($__comd) ?>)</p>
            <?php require_once __DIR__ . '/redes-sociais.php'; ?>
            <?= $__pdoRod ? redes_sociais_render($__pdoRod) : '' ?>
        </div>
        <div class="rodape-coluna">
            <p><strong>Informação legal</strong></p>
            <p><a href="privacidade.php">Política de Privacidade (RGPD)</a></p>
            <p><a href="cookies.php">Política de Cookies</a></p>
            <p><a href="aviso-legal.php">Aviso legal e reclamações</a></p>
            <p><a href="https://www.livroreclamacoes.pt" target="_blank" rel="noopener">Livro de Reclamações eletrónico</a></p>
            <p class="ajuda">Livro de Reclamações físico disponível na receção.</p>
        </div>
        <div class="rodape-coluna">
            <p class="ajuda"><?= e(conteudo('rodape_aviso', 'Os conteúdos deste site têm carácter informativo e não substituem uma consulta de avaliação clínica. Os preços apresentados, quando indicados, são meramente indicativos.')) ?></p>
            <p class="ajuda">&copy; <?= date('Y') ?> <?= e($__cnome ?? CLINICA_NOME) ?> · Todos os direitos reservados.</p>
        </div>
    </div>
</footer>

<?php
require_once __DIR__ . '/../core/cookies.php';
require __DIR__ . '/banner-cookies.php';
?>

<script>
(function () {
    var drop = document.querySelector('.menu-dropdown');
    if (!drop) { return; }
    var botao = drop.querySelector('.menu-dropdown-botao');
    if (!botao) { return; }
    var lista = drop.querySelector('.menu-dropdown-lista');

    function fechar(focusBotao) {
        drop.classList.remove('aberto');
        botao.setAttribute('aria-expanded', 'false');
        if (focusBotao) botao.focus();
    }
    function abrir() {
        drop.classList.add('aberto');
        botao.setAttribute('aria-expanded', 'true');
    }

    botao.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var aberto = drop.classList.contains('aberto');
        if (aberto) fechar(false); else abrir();
    });

    document.addEventListener('click', function (e) {
        if (!drop.contains(e.target)) fechar(false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drop.classList.contains('aberto')) {
            fechar(true);
        }
    });
    drop.addEventListener('focusout', function () {
        setTimeout(function () {
            if (!drop.contains(document.activeElement)) fechar(false);
        }, 0);
    });
})();
</script>
<script src="js/animacoes.js" defer></script>
<script>
(function(){function r(){document.querySelectorAll('[data-animar]:not(.animado)').forEach(function(el){el.classList.add('animado');});}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',function(){setTimeout(r,2500);});}else{setTimeout(r,2500);}
window.addEventListener('load',function(){setTimeout(r,800);});
})();
</script>
</body>
</html>