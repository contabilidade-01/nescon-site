<?php
/*
 * Executor das tarefas de "Pedir artigo" pelo cron do servidor. Na Hostinger:
 * hPanel > Avançado > Cron Jobs, a cada minuto:
 *
 *   php /caminho/do/site/wp-content/themes/nescon-site/tarefas.php
 *
 * Pela linha de comando não há o corte de cerca de 2 minutos que a
 * hospedagem aplica às tarefas disparadas pelo site.
 */

if ('cli' !== PHP_SAPI) {
    exit;
}

$raiz = __DIR__;
while (!is_file($raiz . '/wp-load.php') && dirname($raiz) !== $raiz) {
    $raiz = dirname($raiz);
}
require $raiz . '/wp-load.php';

if (function_exists('nescon_tarefas_executar')) {
    nescon_tarefas_executar();
}
