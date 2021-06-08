<?php

require './router.php';

$method = $_SERVER["REQUEST_METHOD"];
$parsed = parse_url($_SERVER['REQUEST_URI']);
$path = $parsed['path'];

// Útvonalak regisztrálása
$routes = [
    // [method, útvonal, handlerFunction],
    ['GET', '/', 'homeHandler'],

    ['GET', '/bajnoksag/{championshipId}', 'championshipHandler'],
    ['GET', '/uj-meccsek/{championshipId}', 'createMatchesFormHandler'],
    ['POST', '/uj-meccsek/{championshipId}', 'addMatchesFormHandler'],
    ['GET', '/meccs-szerkesztes/{matchId}', 'editMatchHandler'],
    ['POST', '/meccs-szerkesztes/{matchId}', 'updateMatchHandler'],
    ['POST', '/meccs-torles/{matchId}', 'deleteMatchHandler'],
    
    ['POST', '/tippek-leadasa', 'tipsHandler'],
    ['POST', '/tipp-torles/{tipId}', 'tipDeleteHandler'],

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



function tipsHandler()
{
    $pdo = getConnection();
    // echo "<pre>";
    // var_dump($_POST);

    foreach ($_POST["meccs-id"] as $i => $meccs) {
        // echo "Bajnokság: ". $_POST["bajnoksag-id"] . " - Meccs: " . $_POST["meccs-id"][$i] . " - Hazai: " . $_POST["hazai-eredmeny"][$i] . " - Vendég: " . $_POST["vendeg-eredmeny"][$i];
        // echo "<br>";
        $stmt = $pdo->prepare("UPDATE tippek SET hazaiEredmeny = :hazaiEredmeny, vendegEredmeny = :vendegEredmeny WHERE meccsId = :meccsId AND jatekosId = 1");
        $stmt->execute([
            ":hazaiEredmeny" => $_POST["hazai-eredmeny"][$i],
            ":vendegEredmeny" => $_POST["vendeg-eredmeny"][$i],
            ":meccsId" => $_POST["meccs-id"][$i]
        ]);
    }
    header("Location: /bajnoksag/" . $_POST["bajnoksag-id"]);
}


function tipDeleteHandler($urlParams)
{
    $pdo = getConnection();

    $stmt = $pdo->prepare("UPDATE tippek SET hazaiEredmeny = NULL, vendegEredmeny = NULL WHERE id = :id");
    $stmt->execute([":id" => $urlParams["tipId"]]);

    header("Location: {$_SERVER["HTTP_REFERER"]}");


}


function championshipHandler($urlParams)
{
    $pdo = getConnection();
    $championship = getChampionshipById($pdo, $urlParams["championshipId"]);
    $players = getAllPlayersByChampionshipId($pdo, $urlParams["championshipId"]);
    $activeTips = getAllActiveTips($pdo, $urlParams["championshipId"]);
    $givenTips = getAllGivenTips($pdo, $urlParams["championshipId"]);
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
            "players" => $players,
            "activeTips" => $activeTips,
            "givenTips" => $givenTips
        ]),
    ]);
}

function createMatchesFormHandler($urlParams)
{
    $pdo = getConnection();
    $teams = getAllTeams($pdo);
    echo render("wrapper.phtml", [
        "content" => render("uj-meccsek.phtml", [
            "championshipId" => $urlParams["championshipId"],
            "teams" => $teams
        ])
    ]);
}

function editMatchHandler($urlParams)
{
    $pdo = getConnection();
    
    $stmt = $pdo->prepare("SELECT M.*, CS.nev AS hazai, CSA.nev AS vendeg FROM meccsek M LEFT JOIN csapatok CS on CS.id = M.hazaiCsapatId LEFT JOIN csapatok CSA on CSA.id = M.vendegCsapatId WHERE M.Id = :id");
    $stmt->execute([":id" => $urlParams["matchId"]]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);

    echo render("wrapper.phtml", [
        "content" => render("meccs-modositas.phtml", [
            "match" => $match,
        ])
    ]);
}

function updateMatchHandler($urlParams)
{
    $pdo = getConnection();

    $stmt = $pdo->prepare("UPDATE meccsek SET hazaiEredmeny = :hazaiEredmeny, vendegEredmeny = :vendegEredmeny, lejatszott = 1 WHERE Id = :id");
    $stmt->execute([
        ":hazaiEredmeny" => $_POST["hazai-eredmeny"],
        ":vendegEredmeny" => $_POST["vendeg-eredmeny"],
        ":id" => $urlParams["matchId"]
    ]);

    $stmt = $pdo->prepare("SELECT * FROM tippek WHERE meccsId = :meccsId");
    $stmt->execute([
        ":meccsId" => $urlParams["matchId"]
    ]);
    $meccsTippek = $stmt->fetchAll(PDO::FETCH_ASSOC);
    



    foreach ($meccsTippek as $tipp) {
        if (!is_null($tipp["hazaiEredmeny"]) && !is_null($tipp["vendegEredmeny"]) ) {
            if ($_POST["hazai-eredmeny"] == $tipp["hazaiEredmeny"] && $_POST["vendeg-eredmeny"] == $tipp["vendegEredmeny"]) {
                $sql = "UPDATE tippek SET pont = 3 WHERE meccsId = :meccsId AND jatekosId = :jatekosId";
            } elseif (($_POST["hazai-eredmeny"] > $_POST["vendeg-eredmeny"] &&  $tipp["hazaiEredmeny"] > $tipp["vendegEredmeny"]) || ($_POST["hazai-eredmeny"] < $_POST["vendeg-eredmeny"] &&  $tipp["hazaiEredmeny"] < $tipp["vendegEredmeny"]) || ($_POST["hazai-eredmeny"] == $_POST["vendeg-eredmeny"] &&  $tipp["hazaiEredmeny"] == $tipp["vendegEredmeny"])) {
                $sql = "UPDATE tippek SET pont = 1 WHERE meccsId = :meccsId AND jatekosId = :jatekosId";
            } else {
                $sql = "UPDATE tippek SET pont = 0 WHERE meccsId = :meccsId AND jatekosId = :jatekosId";
            }
        } else {
            $sql = "UPDATE tippek SET pont = 0 WHERE meccsId = :meccsId AND jatekosId = :jatekosId";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":meccsId" => $tipp["meccsId"],
            ":jatekosId" => $tipp["jatekosId"],

        ]);
    };
    
    $stmt = $pdo->prepare("SELECT bajnoksagId FROM meccsek WHERE Id = :id");
    $stmt->execute([":id" => $urlParams["matchId"]]);
    
    header("Location: /bajnoksag/" . $meccsTippek[0]["bajnoksagId"]);


}

function deleteMatchHandler($urlParams)
{
    // Meccs törlése a meccsek táblából
    $pdo = getConnection();
    $stmt = $pdo->prepare("DELETE FROM meccsek WHERE id = :id");
    $stmt->execute([":id" => $urlParams["matchId"]]);
    
    // Tippek törlése
    $stmt = $pdo->prepare("DELETE FROM tippek WHERE meccsid = :id");
    $stmt->execute([":id" => $urlParams["matchId"]]);

    header("Location: {$_SERVER["HTTP_REFERER"]}");
}

function addMatchesFormHandler($urlParams)
{
    $pdo = getConnection();
    $players = getAllPlayersByChampionshipId($pdo, $urlParams["championshipId"]);


    $hazaiCsapatok = $_POST["hazai"];
    $vendegcsapatok = $_POST["vendeg"];
    $csoportok = $_POST["csoport"];
    $kezdesek = $_POST["kezdes"];
    foreach ($hazaiCsapatok as $i => $hazai) {
        // echo $hazai. " - " . $vendegcsapatok[$i] . " csoport: " . $csoportok[$i] . " kezdés: " . $kezdesek[$i];
        // echo "<br>";
        $stmt = $pdo->prepare("INSERT INTO meccsek (bajnoksagId, csoport, hazaiCsapatId, vendegCsapatId, kezdes, lejatszott) VALUES (:bajnoksagId, :csoport, :hazaiCsapatId, :vendegCsapatId, :kezdes, 0)");
        $stmt->execute([
            ":bajnoksagId" => $urlParams["championshipId"],
            ":csoport" => $csoportok[$i],
            ":hazaiCsapatId" => $hazai,
            ":vendegCsapatId" => $vendegcsapatok[$i],
            ":kezdes" => $kezdesek[$i],
        ]);

        $matchId = $pdo->lastInsertId();
        foreach ($players as $player) {
            $stmt = $pdo->prepare("INSERT INTO tippek (bajnoksagId, meccsId, jatekosId) VALUES (:bajnoksagId, :meccsId, :jatekosId)");
            $stmt->execute([
                ":bajnoksagId" => $urlParams["championshipId"],
                ":meccsId" => $matchId,
                ":jatekosId" => $player["id"]
            ]);
        };
    };
    header("Location: /bajnoksag/" .  $urlParams["championshipId"]);
}

function getAllChampionship($pdo)
{
    $stmt = $pdo->prepare("SELECT * FROM bajnoksagok");
    $stmt->execute();
    $championships = $stmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($championships as $i => $championship) {
        $championships[$i]["players"] = getAllPlayersByChampionshipId($pdo, $championships[$i]["id"]);
    }
    
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
    WHERE M.lejatszott = 0 AND M.bajnoksagId = :id
    ORDER BY Kezdes desc");
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

function getAllTeams($pdo)
{
    $stmt = $pdo->prepare("SELECT * FROM csapatok");
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $teams;
}

function getAllActiveTips($pdo, $championshipId)
{
    $stmt = $pdo->prepare("SELECT T.*, M.Id, M.kezdes, CS.nev AS hazai, CSA.nev AS vendeg FROM meccsek M
    JOIN tippek T ON T.meccsId = M.Id
    JOIN csapatok CS ON CS.id = M.hazaiCsapatId
    JOIN csapatok CSA ON CSA.id = M.vendegCsapatId
    WHERE T.bajnoksagId = :bajnoksagId
    AND M.lejatszott = 0
    AND NOW() < M.kezdes - INTERVAL 1 HOUR
    AND T.jatekosId = 1
    AND T.hazaiEredmeny IS NULL;");

    $stmt->execute([
        ":bajnoksagId" => $championshipId
    ]);

    $activeTips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $activeTips;
}

function getAllGivenTips($pdo, $championshipId)
{
    $stmt = $pdo->prepare("SELECT T.*, M.Id, M.lejatszott, CS.nev AS hazai, CSA.nev AS vendeg FROM meccsek M
    JOIN tippek T ON T.meccsId = M.Id
    JOIN csapatok CS ON CS.id = M.hazaiCsapatId
    JOIN csapatok CSA ON CSA.id = M.vendegCsapatId
    WHERE T.bajnoksagId = :bajnoksagId
    AND T.jatekosId = 1
    AND T.hazaiEredmeny IS NOT NULL;");

    $stmt->execute([
        ":bajnoksagId" => $championshipId
    ]);

    $givenTips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $givenTips;
}