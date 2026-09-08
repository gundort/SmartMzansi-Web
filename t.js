const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const pupils = document.querySelectorAll('.pupil');
const togglePassword = document.getElementById('togglePassword');

let eyesClosed = false;

// Eye movement while typing email
emailInput.addEventListener('input', (e) => {
    if (eyesClosed) openEyes();

    const length = e.target.value.length;
    const offsetX = Math.min(length * 2, 8); // max 8px movement
    pupils.forEach(pupil => {
        pupil.style.transform = `translateX(${offsetX}px)`;
    });
});

// Eyes close when typing password
passwordInput.addEventListener('focus', () => {
    closeEyes();
});

passwordInput.addEventListener('blur', () => {
    openEyes();
});

function closeEyes() {
    eyesClosed = true;
    pupils.forEach(p => p.style.opacity = '0');
}

function openEyes() {
    eyesClosed = false;
    pupils.forEach(p => p.style.opacity = '1');
}

// Peek with one eye on show password
togglePassword.addEventListener('click', () => {
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';

    if (!isPassword) {
        pupils.forEach((p, i) => p.style.opacity = i === 0 ? '1' : '0'); // peek with left eye
    } else {
        closeEyes(); // back to closed eyes
    }
});