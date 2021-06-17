<?php


function tipsHandler()
{
    $pdo = getConnection();

    if (!isLoggedIn()) {
        echo render("wrapper.phtml", [
            "content" => render("loginForm.phtml")
        ]);
        return;
    }

    // Ha egy órán belül van a meccs kezdése, akkor ne lehessen tippet leadni
    foreach ($_POST["meccs-id"] as $i => $meccs) {
        if (strtotime($_POST["kezdes"][$i]) - 3600 > time()) {
            $stmt = $pdo->prepare("UPDATE tippek SET hazaiEredmeny = :hazaiEredmeny, vendegEredmeny = :vendegEredmeny WHERE meccsId = :meccsId AND jatekosId = :jatekosId");
            $stmt->execute([
                ":hazaiEredmeny" => $_POST["hazai-eredmeny"][$i],
                ":vendegEredmeny" => $_POST["vendeg-eredmeny"][$i],
                ":meccsId" => $_POST["meccs-id"][$i],
                ":jatekosId" => (int)$_SESSION["userId"]
            ]);
        }
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

function getAllTipsByChampionshipIdAndPlayerId($pdo, $championshipId, $playerId)
{
    $stmt = $pdo->prepare("SELECT T.*, CS.nev AS hcs, CSA.nev AS vcs FROM tippek T
    JOIN meccsek M ON M.Id = T.meccsId
    JOIN csapatok CS ON CS.id = M.hazaiCsapatId
    JOIN csapatok CSA ON CSA.id = M.vendegCsapatId
    JOIN jatekosok J ON J.id = T.jatekosId
    WHERE T.bajnoksagId = :championshipId 
    AND M.lejatszott = 1
    AND J.id = :playerId
    ORDER BY T.meccsId DESC");
    $stmt->execute([
        ":championshipId" => $championshipId,
        ":playerId" => $playerId
    ]);

    $tips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $tips;
}

function getPointsByPlayerId($pdo, $championshipId, $playerId)
{
    $stmt = $pdo->prepare(
        "SELECT 
            SUM(T.pont) AS osszpont,
            COUNT(CASE WHEN T.pont = 3 THEN 1 END) AS telitalalat
        FROM tippek T
        JOIN jatekosok J ON J.id = T.jatekosId
        WHERE J.id = :jatekosId
        AND T.bajnoksagId = :bajnoksagId
    ");

    $stmt->execute([
        ":bajnoksagId" => $championshipId,
        ":jatekosId" => $playerId
    ]);

    $pointsAndBullsEye = $stmt->fetch(PDO::FETCH_ASSOC);

    return $pointsAndBullsEye;
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
        AND T.jatekosId = :jatekosId
        AND T.hazaiEredmeny IS NULL;");

    $stmt->execute([
        ":bajnoksagId" => $championshipId,
        ":jatekosId" => (int)$_SESSION["userId"]
    ]);


    $activeTips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $activeTips;
}

function getOwnGivenTips($pdo, $championshipId)
{
    $stmt = $pdo->prepare(
        "SELECT 
            M.Id, 
            CONCAT(CS.nev, ' - ', CSA.nev) AS meccs,
            CONCAT(T.hazaiEredmeny, ' - ', T.vendegEredmeny) AS tipp, 
            M.lejatszott,
            T.id as tippId
            FROM meccsek M
            JOIN tippek T ON T.meccsId = M.Id
            JOIN csapatok CS ON CS.id = M.hazaiCsapatId
            JOIN csapatok CSA ON CSA.id = M.vendegCsapatId
            WHERE T.bajnoksagId = :bajnoksagId
            AND T.jatekosId = :jatekosId
            AND T.hazaiEredmeny IS NOT NULL
            ORDER BY T.meccsId DESC;");

    $stmt->execute([
        ":bajnoksagId" => $championshipId,
        ":jatekosId" => (int)$_SESSION["userId"]
    ]);

    $givenTips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $givenTips;
}

function getOthersGivenTips($pdo, $championshipId)
{
    $stmt = $pdo->prepare(
        "SELECT 
            M.Id,
            CONCAT(H.nev, ' - ', V.nev) AS meccs,
            GROUP_CONCAT(CONCAT(J.nev, ': ', T.hazaiEredmeny, ' - ', T.vendegEredmeny) ORDER BY J.Id SEPARATOR ' | ') AS tippek 
        FROM meccsek M
        JOIN tippek T ON T.meccsId = M.Id
        JOIN jatekosok J on J.id = T.jatekosId
        JOIN csapatok H ON H.id = M.hazaiCsapatId
        JOIN csapatok V on V.id = M.vendegCsapatId
        WHERE J.id != :jatekosId
        AND T.bajnoksagId = :bajnoksagId
        GROUP by M.Id
        ORDER BY M.Id desc");
    
    $stmt->execute([
        ":bajnoksagId" => $championshipId,
        ":jatekosId" => (int)$_SESSION["userId"]
    ]);

    $othersGivenTips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $othersGivenTips;
}
