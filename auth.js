const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirmPassword');
const pupils = document.querySelectorAll('.pupil');
const togglePassword = document.getElementById('togglePassword');
const toggleButtons = document.querySelectorAll('.toggle-buttons button');
const roleSection = document.querySelector('.roles');
const confirmPasswordContainer = document.querySelector('.confirm-password-container');
const loginBtn = document.querySelector('.login-btn');

let eyesClosed = false;

// Move pupils with email typing
emailInput.addEventListener('input', (e) => {
    if (eyesClosed) openEyes();

    const length = e.target.value.length;
    const offsetX = Math.min(length * 2, 8);
    pupils.forEach(p => {
        p.style.transform = `translateX(${offsetX}px)`;
    });
});

// Close eyes when typing password
passwordInput.addEventListener('focus', closeEyes);
passwordInput.addEventListener('blur', openEyes);

function closeEyes() {
    eyesClosed = true;
    pupils.forEach(p => p.style.opacity = '0');
}

function openEyes() {
    eyesClosed = false;
    pupils.forEach(p => p.style.opacity = '1');
}

// Wink on toggle
togglePassword.addEventListener('click', () => {
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';

    if (isPassword) {
        pupils.forEach((p, i) => p.style.opacity = i === 0 ? '1' : '0'); // wink
    } else {
        closeEyes();
    }
});

// Toggle login/register
const form = document.getElementById("mainForm");

toggleButtons.forEach((btn, index) => {
    btn.addEventListener("click", () => {
        toggleButtons.forEach(b => b.classList.remove("active"));
        btn.classList.add("active");

        if (index === 1) {
            // Register mode
            loginBtn.textContent = "Register";
            form.action = "Database/register.php";
            roleSection.style.display = "flex";
            confirmPasswordContainer.style.display = "block";
        } else {
            // Login mode
            loginBtn.textContent = "Log in";
            form.action = "Database/login.php";
            roleSection.style.display = "none";
            confirmPasswordContainer.style.display = "none";
        }
    });
});
form.addEventListener("submit", function(e) {
    if (form.action.includes("register.php")) {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (password !== confirmPassword) {
            e.preventDefault(); // stop form from submitting
            alert("Passwords do not match!");
        }
    }
});