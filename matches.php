<?php

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
        if (!is_null($tipp["hazaiEredmeny"]) && !is_null($tipp["vendegEredmeny"])) {
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


function getChampionsihpActiveMatches($pdo, $championshipId)
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

function getChampionshipClosedMatches($pdo, $championshipId)
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