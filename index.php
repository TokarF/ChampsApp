<?php

require "./db_conf.php";
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
    ['POST', '/tipp-leadasa/{tipId}', 'tipHandler'],
    ['POST', '/tipp-torles/{tipId}', 'tipDeleteHandler'],

    // Registraion
    ['GET', '/registration', 'registrationFormHandler'],
    ['POST', '/registration', 'registrationHandler'],

    // Login
    ['GET', '/login', 'loginFormHandler'],
    ['POST', '/login', 'loginHandler'],
    ['POST', '/logout', 'logoutHandler'],


    // AJAX
    ['GET', '/tippek-ajax/{championshipId}', 'tipHandlerAjax']
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
