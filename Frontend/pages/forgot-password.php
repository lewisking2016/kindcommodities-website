<?php
/**
 * Customer Forgot Password Page
 * Checks the database for the email and shows appropriate contact instructions.
 */
declare(strict_types=1);

$path_prefix = '../';
$page_title = 'Recover Password | Kind Commodities Ltd';

include '../includes/header.php';

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check whether this email exists in the users table
        $pdo = function_exists('getDB') ? getDB() : null;
        $found = false;
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $found = (bool)$stmt->fetchColumn();
            } catch (\Throwable $e) {
                // DB error — treat as not-found, show support contact
            }
        }
        if ($found) {
            $message = 'We found your account. Because automated email recovery is not yet active, '
                     . 'please contact our support team directly to reset your password:<br><br>'
                     . '<strong>Kenya:</strong> <a href="tel:+254726012845">+254 726 012 845</a> / '
                     . '<a href="tel:+254777012845">+254 777 012 845</a><br>'
                     . '<strong>Rwanda:</strong> <a href="tel:+250782273747">+250 782 273 747</a><br>'
                     . '<strong>Burundi:</strong> <a href="tel:+25762903055">+257 62 903 055</a><br><br>'
                     . 'Quote your registered email: <strong>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</strong>';
        } else {
            $error = 'No account found with that email address. '
                   . 'If you registered recently, please try a different email or '
                   . '<a href="/Frontend/pages/register.php" style="color:var(--primary);font-weight:600;">create a new account</a>.';
        }
    }
}
?>

<section style="padding: 120px 0 var(--space-4xl); background: #f8fafc; min-height: 70vh; display: flex; align-items: center;">
    <div class="container" style="display: flex; justify-content: center;">
        <div class="admin-card" style="width: 100%; max-width: 450px; padding: 40px; border-radius: var(--radius-lg); border: 1px solid rgba(0,0,0,0.06); background: #ffffff;">
            
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="font-family: 'Outfit', sans-serif; font-weight: 800; margin-bottom: 8px;">Recover Password</h2>
                <p style="color: var(--gray-600); font-size: 0.95rem;">Enter your registered email address and we will provide password reset instructions.</p>
            </div>

            <?php if ($message): ?>
                <div style="padding: 14px; background: #D3E8B8; color: #2C6B31; border-radius: var(--radius-md); font-size: 0.9rem; margin-bottom: 20px; font-weight: 500; border-left: 4px solid #3E8A3A;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="padding: 14px; background: #fee2e2; color: #b91c1c; border-radius: var(--radius-md); font-size: 0.9rem; margin-bottom: 20px; font-weight: 500; border-left: 4px solid #dc2626;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; font-size: 0.85rem; color: var(--dark); margin-bottom: 6px;">Email Address</label>
                    <input type="email" name="email" class="form-control" style="width: 100%; height: 44px; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: var(--radius-md);" placeholder="e.g. user@example.com" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; height: 44px; justify-content: center; font-weight: 700; border-radius: var(--radius-md); margin-bottom: 20px;">
                    Send Reset Link
                </button>
            </form>

            <div style="text-align: center; font-size: 0.88rem; color: var(--gray-600);">
                Remember your password? <a href="/Frontend/pages/login.php" style="color: var(--primary); font-weight: 600; text-decoration: none;">Login here</a>
            </div>

        </div>
    </div>
</section>

<?php
include '../includes/footer.php';
?>
