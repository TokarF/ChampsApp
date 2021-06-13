<?php

function championshipHandler($urlParams)
{
    if (!isLoggedIn()) {
        echo render("wrapper.phtml", [
            "content" => render("loginForm.phtml")
        ]);
        return;
    }

    $pdo = getConnection();
    $championship = getChampionshipById($pdo, $urlParams["championshipId"]);
    $players = getAllPlayersByChampionshipId($pdo, $urlParams["championshipId"]);
    $activeTips = getAllActiveTips($pdo, $urlParams["championshipId"]);
    $givenTips = getAllGivenTips($pdo, $urlParams["championshipId"]);
    // echo "<pre>";
    // var_dump($players);
 




    echo render("wrapper.phtml", [
        "content" => render("bajnoksag.phtml", [
            "championship" => $championship,
            "players" => $players,
            "activeTips" => $activeTips,
            "givenTips" => $givenTips
        ]),
    ]);
}



function getAllChampionship($pdo)
{
    $stmt = $pdo->prepare("SELECT * FROM bajnoksagok");
    $stmt->execute();
    $championships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($championships as $i => $championship) {
        $championships[$i]["players"] = getAllPlayersByChampionshipId($pdo, $championships[$i]["id"]);
        $championships[$i]["closedMatchesCount"] = count(getChampionshipClosedMatches($pdo,  $championships[$i]["id"]));
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
    $championship["aktiv-meccsek"] = getChampionsihpActiveMatches($pdo, $championshipId);
    $championship["lejatszott-meccsek"] = getChampionshipClosedMatches($pdo, $championshipId);
    return $championship;
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
    
    foreach ($players as $key => $player) {
        $players[$key]["pontok"] = getPointsByPlayerId($pdo, $player["bajnoksagId"], $player["id"]);
        $players[$key]["tippek"] = getAllTipsByChampionshipIdAndPlayerId($pdo, $player["bajnoksagId"], $player["id"]);

    }

    array_multisort(
        array_map(function ($player) {
            return $player['pontok']["osszpont"];
        }, $players),
        SORT_DESC,
        array_map(function ($player) {
            return $player['pontok']["telitalalat"];
        }, $players),
        SORT_DESC,
        $players
    );

    return $players;
}

function getAllTeams($pdo)
{
    $stmt = $pdo->prepare("SELECT * FROM csapatok");
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $teams;
}
