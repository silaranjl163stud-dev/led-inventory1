<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — JMACX LED Inventory</title>
    <meta name="description" content="Sign in to the JMACX LED Inventory Management System.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body>
    <div class="login-page">
        <div class="login-box">
            <div class="login-logo">
                <div class="login-logo-icon">
                    <ion-icon name="layers-outline"></ion-icon>
                </div>
                <div>
                    <div class="login-brand-name">JMACX LED</div>
                </div>
            </div>

            <div id="login-view">
                <div class="login-title">Welcome back</div>
                <div class="login-sub">Sign in to access the inventory system</div>

                <form id="login-form">
                    <div class="form-group">
                        <label class="form-label" for="login-username">Username</label>
                        <input class="form-input" type="text" id="login-username" autocomplete="username" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="login-password">Password</label>
                        <input class="form-input" type="password" id="login-password" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn primary login-btn-full" id="login-submit-btn">
                        <ion-icon name="log-in-outline"></ion-icon>
                        Sign In
                    </button>
                </form>
                <button type="button" class="btn login-btn-full" id="show-register-btn">Create an account</button>
            </div>

            <div id="register-view" hidden>
                <div class="login-title">Create account</div>
                <div class="login-sub">New accounts receive Customer access and can submit repair tickets.</div>

                <form id="register-form">
                    <div class="form-group">
                        <label class="form-label" for="register-full-name">Full name</label>
                        <input class="form-input" type="text" id="register-full-name" maxlength="100" autocomplete="name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="register-username">Username</label>
                        <input class="form-input" type="text" id="register-username" maxlength="50" autocomplete="username" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="register-email">Email (optional)</label>
                        <input class="form-input" type="email" id="register-email" maxlength="150" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="register-password">Password</label>
                        <input class="form-input" type="password" id="register-password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="register-password-confirm">Confirm password</label>
                        <input class="form-input" type="password" id="register-password-confirm" minlength="8" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn primary login-btn-full" id="register-submit-btn">Register</button>
                </form>
                <button type="button" class="btn login-btn-full" id="show-login-btn">Back to sign in</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast hidden"></div>

    <script src="assets/js/api.js"></script>
    <script src="assets/js/toast.js"></script>
    <script>
        // If already logged in, skip login
        (async () => {
            const token = localStorage.getItem('auth_token');
            if (token) {
                try {
                    const res = await fetch('api/auth/me', {
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    if (res.ok) {
                        window.location.href = 'dashboard.php';
                        return;
                    }
                } catch (e) {}
                localStorage.removeItem('auth_token');
            }
        })();

        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('login-submit-btn');
            btn.disabled = true;
            btn.textContent = 'Signing in...';

            const username = document.getElementById('login-username').value.trim();
            const password = document.getElementById('login-password').value;

            const res = await api.login(username, password);
            if (res && res.token) {
                localStorage.setItem('auth_token', res.token);
                window.location.href = 'dashboard.php';
            } else {
                Toast.show('Invalid username or password', 'error');
                btn.disabled = false;
                btn.innerHTML = '<ion-icon name="log-in-outline"></ion-icon> Sign In';
            }
        });

        const loginView = document.getElementById('login-view');
        const registerView = document.getElementById('register-view');

        document.getElementById('show-register-btn').addEventListener('click', () => {
            loginView.hidden = true;
            registerView.hidden = false;
        });

        document.getElementById('show-login-btn').addEventListener('click', () => {
            registerView.hidden = true;
            loginView.hidden = false;
        });

        document.getElementById('register-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = document.getElementById('register-password').value;
            const confirmPassword = document.getElementById('register-password-confirm').value;
            if (password !== confirmPassword) {
                Toast.show('Passwords do not match', 'error');
                return;
            }

            const btn = document.getElementById('register-submit-btn');
            btn.disabled = true;
            btn.textContent = 'Registering...';

            const result = await api.register({
                full_name: document.getElementById('register-full-name').value.trim(),
                username: document.getElementById('register-username').value.trim(),
                email: document.getElementById('register-email').value.trim(),
                password
            });

            if (result) {
                document.getElementById('login-username').value = document.getElementById('register-username').value.trim();
                document.getElementById('register-form').reset();
                registerView.hidden = true;
                loginView.hidden = false;
                Toast.show('Account created. You can now sign in.');
            } else {
                Toast.show('Registration failed. Check your details or try another username/email.', 'error');
            }

            btn.disabled = false;
            btn.textContent = 'Register';
        });
    </script>
</body>
</html>
 