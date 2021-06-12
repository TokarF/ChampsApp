<?php

if ($_SERVER['DEPLOYMENT_MODE'] === 'DEV') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

require './router.php';
require './championship.php';
require './loginandregister.php';
require './matches.php';
require './tips.php';

$method = $_SERVER["REQUEST_METHOD"];
$parsed = parse_url($_SERVER['REQUEST_URI']);
$path = $parsed['path'];

// Útvonalak regisztrálása
$routes = [
    // [method, útvonal, handlerFunction],
    ['GET', '/', 'homeHandler'],

    // Championship
    ['GET', '/bajnoksag/{championshipId}', 'championshipHandler'],

    // Matches
    ['GET', '/uj-meccsek/{championshipId}', 'createMatchesFormHandler'],
    ['POST', '/uj-meccsek/{championshipId}', 'addMatchesFormHandler'],
    ['GET', '/meccs-szerkesztes/{matchId}', 'editMatchHandler'],
    ['POST', '/meccs-szerkesztes/{matchId}', 'updateMatchHandler'],
    ['POST', '/meccs-torles/{matchId}', 'deleteMatchHandler'],

    // Tips
    ['POST', '/tippek-leadasa', 'tipsHandler'],
    ['POST', '/tipp-torles/{tipId}', 'tipDeleteHandler'],

    // Registraion
    ['GET', '/registration', 'registrationFormHandler'],
    ['POST', '/registration', 'registrationHandler'],

    // Login
    ['GET', '/login', 'loginFormHandler'],
    ['POST', '/login', 'loginHandler'],
    ['POST', '/logout', 'logoutHandler'],
];

// Útvonalválasztó inicializálása
$dispatch = registerRoutes($routes);
$matchedRoute = $dispatch($method, $path);
$handlerFunction = $matchedRoute['handler'];
$handlerFunction($matchedRoute['vars']);



// Handler függvények deklarálása
function homeHandler()
{
    $pdo = getConnection();
    $championships = getAllChampionship($pdo);

    if (!isLoggedIn()) {
        echo render("wrapper.phtml", [
            "content" => render("loginForm.phtml")
        ]);
        return;
    }

    echo render("wrapper.phtml", [
        "content" => render("fooldal.phtml", [
            "championships" => $championships,
        ]),
    ]);
}

function render($path, $params = [])
{
    ob_start();
    require __DIR__ . '/views/' . $path;
    return ob_get_clean();
}

function getConnection()
{
/*    return new PDO(
	              "'mysql:host=' . $_SERVER['DB_HOST'] . ';dbname=' . $_SERVER['DB_NAME'],
                        $_SERVER['DB_USER'],
                        $_SERVER['DB_PASSWORD']"
                );
*/
    $dsn = "mysql:host=localhost;dbname=c26268goaltoto";
    $user = "c26268feri";
    $passwd = "egyketto12";


    return new PDO(
	              $dsn, $user, $passwd
                );

}


