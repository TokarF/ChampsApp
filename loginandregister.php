<?php
function loginFormHandler()
{
    echo render("wrapper.phtml", [
        "content" => render("loginForm.phtml", []),
    ]);
}

function loginHandler()
{
    $pdo = getConnection();

    $stmt = $pdo->prepare("SELECT * FROM jatekosok WHERE nev = :nev");
    $stmt->execute([":nev" => $_POST["nev"]]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "invalid";
        return;
    }

    $isVerified = password_verify($_POST["jelszo"], $user["jelszo"]);

    if (!$isVerified) {
        echo "notVerified";
        return;
    }

    session_start();
    $_SESSION["userId"] = $user["id"];
    $_SESSION["userName"] = $user["nev"];
    $_SESSION["role"] = $user["szerepkor"];

    header("Location: /");
}

function logoutHandler()
{
    session_start();
    $params = session_get_cookie_params();
    setcookie(session_name(),  '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
    session_destroy();
    header('Location: /');
}


function registrationFormHandler()
{
    echo render("wrapper.phtml", [
        "content" => render("registrationForm.phtml", []),
    ]);
}

function registrationHandler()
{
    $pdo = getConnection();

    $stmt = $pdo->prepare("INSERT INTO jatekosok (nev, jelszo, szerepkor) VALUES (:nev, :jelszo, 0)");
    $stmt->execute([
        ":nev" => $_POST["nev"],
        ":jelszo" => password_hash($_POST["jelszo"], PASSWORD_DEFAULT)
    ]);

    header("Location: /");
}

function isLoggedIn(): bool
{
    if (!isset($_COOKIE[session_name()])) {
        return false;
    };

    session_start();

    if (!isset($_SESSION["userId"])) {
        return false;
    }

    return true;
}