<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';


/*
|--------------------------------------------------------------------------
| CHECK RESET SESSION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['reset_passenger_id']) ||
    empty($_SESSION['reset_email'])
) {

    header('Location: forgot_password.php');

    exit;
}


$error = '';

$success = false;


/*
|--------------------------------------------------------------------------
| RESET PASSWORD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password =
        $_POST['password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($password === '') {

        $error =
            'Please enter a new password.';

    } elseif (strlen($password) < 8) {

        $error =
            'Password must be at least 8 characters long.';

    } elseif ($confirmPassword === '') {

        $error =
            'Please confirm your new password.';

    } elseif ($password !== $confirmPassword) {

        $error =
            'Passwords do not match.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | HASH NEW PASSWORD
            |--------------------------------------------------------------------------
            */

            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            /*
            |--------------------------------------------------------------------------
            | UPDATE PASSWORD
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE passenger
                SET password_hash = ?
                WHERE passenger_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $passwordHash,
                (int) $_SESSION['reset_passenger_id']
            ]);


            /*
            |--------------------------------------------------------------------------
            | CLEAR RESET SESSION
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION['reset_passenger_id'],
                $_SESSION['reset_email']
            );


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            $success = true;


        } catch (PDOException $e) {

            error_log(
                'VektorSky Reset Password Error: '
                . $e->getMessage()
            );

            $error =
                'Unable to change your password. Please try again.';
        }
    }
}


$pageTitle = 'Reset Password';

require_once __DIR__ . '/includes/header.php';

?>


<style>

.reset-page {

    min-height:
        calc(100vh - 120px);

    display:flex;

    align-items:center;

    justify-content:center;

    padding:50px 20px;

    background:
        linear-gradient(
            135deg,
            #f5f9ff,
            #eef6ff
        );
}


.reset-card {

    width:100%;

    max-width:450px;

    box-sizing:border-box;

    background:#ffffff;

    border-radius:24px;

    padding:40px;

    box-shadow:
        0 25px 70px
        rgba(11,37,69,.13);
}


.reset-icon {

    width:68px;

    height:68px;

    margin:0 auto 20px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:18px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #0ea5e9
        );

    font-size:30px;
}


.reset-title {

    text-align:center;

    margin-bottom:28px;
}


.reset-title h1 {

    margin:0;

    color:#0b2545;

    font-size:30px;

    font-weight:900;
}


.reset-title p {

    margin:10px 0 0;

    color:#667085;

    font-size:14px;

    line-height:1.6;
}


.message {

    padding:14px;

    margin-bottom:20px;

    border-radius:10px;

    font-size:13px;
}


.message.error {

    background:#fef3f2;

    border:1px solid #fecdca;

    color:#b42318;
}


.message.success {

    background:#ecfdf3;

    border:1px solid #abefc6;

    color:#067647;

    text-align:center;
}


.form-group {

    margin-bottom:20px;
}


.form-group label {

    display:block;

    margin-bottom:8px;

    color:#344054;

    font-size:13px;

    font-weight:800;
}


.password-input {

    width:100%;

    box-sizing:border-box;

    padding:14px;

    border:1px solid #d0d5dd;

    border-radius:11px;

    font-size:14px;

    outline:none;
}


.password-input:focus {

    border-color:#2563eb;

    box-shadow:
        0 0 0 4px
        rgba(37,99,235,.10);
}


.change-button {

    width:100%;

    padding:15px;

    border:none;

    border-radius:11px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #0ea5e9
        );

    color:#ffffff;

    font-size:14px;

    font-weight:900;

    cursor:pointer;
}


.change-button:hover {

    opacity:.95;

}


.login-button {

    display:block;

    width:100%;

    box-sizing:border-box;

    margin-top:18px;

    padding:14px;

    border-radius:11px;

    background:#eff6ff;

    color:#2563eb;

    text-align:center;

    text-decoration:none;

    font-size:14px;

    font-weight:800;
}


.login-button:hover {

    background:#dbeafe;

}


.email-display {

    margin-bottom:22px;

    padding:12px;

    border-radius:10px;

    background:#f8fafc;

    color:#667085;

    text-align:center;

    font-size:12px;
}

</style>


<main class="reset-page">

    <div class="reset-card">


        <?php if ($success): ?>


            <div class="reset-icon">
                ✓
            </div>


            <div class="reset-title">

                <h1>
                    Password Changed!
                </h1>

                <p>
                    Your VektorSky password has been
                    successfully changed.
                </p>

            </div>


            <a
                href="login.php"
                class="login-button"
            >

                Continue to Login

            </a>


        <?php else: ?>


            <div class="reset-icon">
                🔐
            </div>


            <div class="reset-title">

                <h1>
                    Reset Password
                </h1>

                <p>
                    Create a new password for your
                    VektorSky account.
                </p>

            </div>


            <div class="email-display">

                Account:
                <strong>
                    <?= h($_SESSION['reset_email']) ?>
                </strong>

            </div>


            <?php if ($error !== ''): ?>

                <div class="message error">

                    ⚠️ <?= h($error) ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
            >


                <div class="form-group">

                    <label for="password">

                        New Password

                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="password-input"
                        placeholder="Enter new password"
                        minlength="8"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="confirm_password">

                        Confirm Password

                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="password-input"
                        placeholder="Enter password again"
                        minlength="8"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="change-button"
                >

                    Change Password

                </button>


            </form>


        <?php endif; ?>


    </div>

</main>


<?php

require_once __DIR__ . '/includes/footer.php';

?>