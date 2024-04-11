GitLab Access Checker
=================

Požadavky
------------

Tento projekt vyžaduje PHP 8.1.


Instalace
------------

Po naklonování nainstalovat závislosti pomocí Composeru:

    composer install

Do enviromentálních proměnných je třeba nastavit přístupový token pro GitLab API např.:

pro Linux / macOS

    export GITLAB_TOKEN=‹value›

pro Windows

    setx GITLAB_TOKEN ‹value>


Spouštění
----------------

Z prohlížeče přes url adresu `http://<HOST>/?id=<TOP_LEVEL_GROUP_ID>` nebo z příkazového řádku

    php /path/to/project/index.php <TOP_LEVEL_GROUP_ID>
