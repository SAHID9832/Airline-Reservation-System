<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {

        $error = 'Please enter your registered email address.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT
                    passenger_id,
                    name,
                    email
                FROM passenger
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $passenger = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$passenger) {

                $error = 'No account found with this email address.';

            } else {

                /*
                 * Store the passenger temporarily in the session.
                 */
                $_SESSION['reset_passenger_id'] =
                    (int) $passenger['passenger_id'];

                $_SESSION['reset_email'] =
                    $passenger['email'];

                /*
                 * Go directly to reset password page.
                 */
                header('Location: reset_password.php');

                exit;
            }

        } catch (PDOException $e) {

            error_log(
                'VektorSky Forgot Password Error: '
                . $e->getMessage()
            );

            $error =
                'Unable to process your request. Please try again.';
        }
    }
}

$pageTitle = 'Forgot Password';

require_once __DIR__ . '/includes/header.php';

?>

<style>

.forgot-page {

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


.forgot-card {

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


.forgot-icon {

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


.forgot-title {

    text-align:center;

    margin-bottom:28px;
}


.forgot-title h1 {

    margin:0;

    color:#0b2545;

    font-size:30px;

    font-weight:900;
}


.forgot-title p {

    margin:10px 0 0;

    color:#667085;

    font-size:14px;

    line-height:1.6;
}


.error-message {

    padding:14px;

    margin-bottom:20px;

    border-radius:10px;

    background:#fef3f2;

    border:1px solid #fecdca;

    color:#b42318;

    font-size:13px;
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


.email-wrapper {

    position:relative;
}


.email-icon {

    position:absolute;

    left:15px;

    top:50%;

    transform:translateY(-50%);

    color:#98a2b3;
}


.email-input {

    width:100%;

    box-sizing:border-box;

    padding:
        14px
        14px
        14px
        42px;

    border:1px solid #d0d5dd;

    border-radius:11px;

    font-size:14px;

    outline:none;
}


.email-input:focus {

    border-color:#2563eb;

    box-shadow:
        0 0 0 4px
        rgba(37,99,235,.10);
}


.reset-button {

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

    color:white;

    font-size:14px;

    font-weight:900;

    cursor:pointer;
}


.reset-button:hover {

    opacity:.95;

    transform:translateY(-1px);
}


.back-login {

    display:block;

    margin-top:22px;

    text-align:center;

    color:#667085;

    font-size:13px;

    text-decoration:none;
}


.back-login:hover {

    color:#2563eb;

}

</style>


<main class="forgot-page">

    <div class="forgot-card">

        <div class="forgot-icon">
            🔑
        </div>


        <div class="forgot-title">

            <h1>
                Forgot Password?
            </h1>

            <p>
                Enter your registered email address
                to reset your VektorSky password.
            </p>

        </div>


        <?php if ($error !== ''): ?>

            <div class="error-message">

                ⚠️ <?= h($error) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
            autocomplete="on"
        >

            <div class="form-group">

                <label for="email">
                    Registered Email
                </label>

                <div class="email-wrapper">

                    <span class="email-icon">
                        ✉
                    </span>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="email-input"
                        placeholder="Enter your registered email"
                        value="<?= h($email) ?>"
                        required
                    >

                </div>

            </div>


            <button
                type="submit"
                class="reset-button"
            >

                Continue to Reset Password

            </button>

        </form>


        <a
            href="login.php"
            class="back-login"
        >

            ← Back to Login

        </a>

    </div>

</main>


<?php

require_once __DIR__ . '/includes/footer.php';

?>