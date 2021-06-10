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

    foreach ($_POST["meccs-id"] as $i => $meccs) {
        $stmt = $pdo->prepare("UPDATE tippek SET hazaiEredmeny = :hazaiEredmeny, vendegEredmeny = :vendegEredmeny WHERE meccsId = :meccsId AND jatekosId = :jatekosId");
        $stmt->execute([
            ":hazaiEredmeny" => $_POST["hazai-eredmeny"][$i],
            ":vendegEredmeny" => $_POST["vendeg-eredmeny"][$i],
            ":meccsId" => $_POST["meccs-id"][$i],
            ":jatekosId" => (int)$_SESSION["userId"]
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

function getAllGivenTips($pdo, $championshipId)
{
    $stmt = $pdo->prepare("SELECT T.*, M.Id, M.lejatszott, CS.nev AS hazai, CSA.nev AS vendeg FROM meccsek M
    JOIN tippek T ON T.meccsId = M.Id
    JOIN csapatok CS ON CS.id = M.hazaiCsapatId
    JOIN csapatok CSA ON CSA.id = M.vendegCsapatId
    WHERE T.bajnoksagId = :bajnoksagId
    AND T.jatekosId = :jatekosId
    AND T.hazaiEredmeny IS NOT NULL;");

    $stmt->execute([
        ":bajnoksagId" => $championshipId,
        ":jatekosId" => (int)$_SESSION["userId"]
    ]);

    $givenTips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $givenTips;
}