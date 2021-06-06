<?php

require './router.php';

$method = $_SERVER["REQUEST_METHOD"];
$parsed = parse_url($_SERVER['REQUEST_URI']);
$path = $parsed['path'];

// Útvonalak regisztrálása
$routes = [
    // [method, útvonal, handlerFunction],
    ['GET', '/', 'homeHandler'],

    ['GET', '/bajnoksag/{championshipId}', 'championshipHandler']

    // // Kategóriák
    // ['GET', '/kategoriak', 'categoriesHandler'],
    // ['GET', '/kategoria/{categoryId}', 'categoryHandler'],
    // ['GET', '/uj-kategoria', 'createCategoryFormHandler'],
    // ['POST', '/uj-kategoria', 'createCategoryHandler'],
    // ['GET', '/kategoria-szerkesztese/{categoryId}', 'editcategoryHandler'],
    // ['POST', '/kategoria-szerkesztese/{categoryId}', 'updatecategoryHandler'],

   
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
    return new PDO("mysql:dbname=goltoto;host=localhost;charset=utf8", "root");
}

function championshipHandler($urlParams)
{
    $pdo = getConnection();
    $championship = getChampionshipById($pdo, $urlParams["championshipId"]);
    $players = getAllPlayersByChampionshipId($pdo, $urlParams["championshipId"]);
    
    // echo "<pre>";
    // var_dump($players);
    foreach ($players as $key => $player) {
        $players[$key]["tippek"] = getAllTipsByChampionshipIdAndPlayerId($pdo, $player["bajnoksagId"], $player["id"]);
        $players[$key]["osszpont"] = array_sum(array_column($players[$key]["tippek"],'pont'));
    }
    usort($players, function ($player1, $player2) {
        return $player2['osszpont'] <=> $player1['osszpont'];
    });
    // var_dump($players);
    // exit;
    

    echo render("wrapper.phtml", [
        "content" => render("bajnoksag.phtml", [
            "championship" => $championship,
            "players" => $players
        ]),
    ]);



}

function getAllChampionship($pdo)
{
    $stmt = $pdo->prepare("SELECT * FROM bajnoksagok");
    $stmt->execute();
    $championships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $championships;
}

function getChampionshipById($pdo, $championshipId)
{
    $stmt = $pdo->prepare("SELECT * FROM bajnoksagok WHERE id = :id");
    $stmt->execute([
        ":id" => $championshipId
    ]);
    $championship = $stmt->fetch(PDO::FETCH_ASSOC);
    $championship["aktiv-meccsek"] = getChampionshpActiveMatches($pdo, $championshipId);
    $championship["lejatszott-meccsek"] = getChampionshpClosedMatches($pdo, $championshipId);
    return $championship;
}

function getChampionshpActiveMatches($pdo, $championshipId)
{
    $stmt = $pdo->prepare("SELECT CS.Nev AS Hazai, CSA.Nev AS Vendeg, M.Id, M.csoport, M.hazaiEredmeny, M.vendegEredmeny, M.Kezdes FROM meccsek M
    LEFT JOIN csapatok CS ON CS.Id = M.hazaiCsapatId
    LEFT JOIN csapatok CSA ON CSA.Id = M.vendegCsapatId
    WHERE M.lejatszott = 0 AND M.bajnoksagId = :id");
    $stmt->execute([
        ":id" => $championshipId
    ]);

    $activeMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $activeMatches;
}

function getChampionshpClosedMatches($pdo, $championshipId)
{
    $stmt = $pdo->prepare("SELECT CS.Nev AS Hazai, CSA.Nev AS Vendeg, M.id, M.csoport, M.hazaiEredmeny, M.vendegEredmeny, M.Kezdes FROM meccsek M
    LEFT JOIN csapatok CS ON CS.Id = M.hazaiCsapatId
    LEFT JOIN csapatok CSA ON CSA.Id = M.vendegCsapatId
    WHERE M.lejatszott = 1 AND M.bajnoksagId = :id");
    $stmt->execute([
        ":id" => $championshipId
    ]);

    $activeMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $activeMatches;
}

function getAllTipsByChampionshipIdAndPlayerId($pdo, $championshipId, $playerId)
{
    $stmt = $pdo->prepare("SELECT T.*, CS.nev AS hcs, CSA.nev AS vcs FROM tippek T
    JOIN meccsek M ON M.Id = T.meccsId
    JOIN csapatok CS ON CS.id = M.hazaiCsapatId
    JOIN csapatok CSA ON CSA.id = M.vendegCsapatId
    join jatekosok J ON J.id = T.jatekosId
    WHERE T.bajnoksagId = :championshipId 
    AND M.lejatszott = 1
    AND j.id = :playerId");
    $stmt->execute([
        ":championshipId" => $championshipId,
        ":playerId" => $playerId
    ]);

    $tips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $tips;
}

function getAllPlayersByChampionshipId($pdo, $championshipId)
{
    $stmt = $pdo->prepare("SELECT J.id, J.nev, R.bajnoksagId FROM resztvevok R 
    JOIN jatekosok J ON J.id = R.jatekosId
    WHERE R.bajnoksagId = :id");
    $stmt->execute([
        ":id" => $championshipId
    ]);

    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $players;
}