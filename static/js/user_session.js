// static/js/user_session.js

(function () {
    // Check if user is logged in
    const currentUser = JSON.parse(localStorage.getItem('currentUser') || 'null');

    if (!currentUser) return; // Not logged in, do nothing

    console.log('User logged in:', currentUser);

    // Inject Styles for Modal
    const style = document.createElement('style');
    style.textContent = `
        .user-icon-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
            cursor: pointer;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .user-icon-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .profile-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        .profile-modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .profile-modal {
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            color: white;
            font-family: 'Inter', sans-serif;
        }
        .profile-modal h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 10px;
            color: white;
            font-size: 0.875rem;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            ring: 2px solid #3b82f6;
        }
        .btn-primary {
            width: 100%;
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: transparent;
            color: #94a3b8;
            border: 1px solid #334155;
            margin-top: 10px;
        }
        .btn-secondary:hover {
            color: white;
            border-color: white;
        }
        .logout-btn {
            margin-top: 20px;
            color: #ef4444;
            font-size: 0.875rem;
            text-align: center;
            cursor: pointer;
        }
        .logout-btn:hover {
            text-decoration: underline;
        }
        .tab-btn {
            padding: 8px 16px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            color: #94a3b8;
        }
        .tab-btn.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #334155;
        }
    `;
    document.head.appendChild(style);

    // Inject Modal HTML
    const modalHtml = `
        <div id="profileModal" class="profile-modal-overlay">
            <div class="profile-modal">
                <div class="flex justify-between items-center mb-4">
                    <h2>Mi Cuenta</h2>
                    <button onclick="closeProfileModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-times"></i></button>
                </div>

                <div class="tabs">
                    <div class="tab-btn active" onclick="switchTab('profile')">Perfil</div>
                    <div class="tab-btn" onclick="switchTab('password')">Contraseña</div>
                </div>

                <!-- Profile Form -->
                <form id="profileForm" onsubmit="updateProfile(event)">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="${currentUser.email}" required>
                    </div>
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="name" placeholder="Tu nombre" value="${currentUser.name || ''}" required>
                    </div>
                    <button type="submit" class="btn-primary">Guardar Cambios</button>
                </form>

                <!-- Password Form -->
                <form id="passwordForm" onsubmit="changePassword(event)" style="display:none;">
                    <div class="form-group">
                        <label>Contraseña Actual</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>Nueva Contraseña</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirmar Nueva Contraseña</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>
                    <button type="submit" class="btn-primary">Cambiar Contraseña</button>
                </form>

                <div class="logout-btn" onclick="logout()">
                    <i class="fa-solid fa-sign-out-alt"></i> Cerrar Sesión
                </div>
                <div id="modalMsg" class="mt-4 text-center text-sm"></div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Global Functions
    window.openProfileModal = async function () {
        document.getElementById('profileModal').classList.add('active');

        // Fetch fresh data
        try {
            const res = await fetch('/backend/profile.php', { credentials: 'include' });
            const result = await res.json();
            if (result.success) {
                const form = document.getElementById('profileForm');
                if (form) {
                    form.querySelector('input[name="name"]').value = result.user.name || '';
                    form.querySelector('input[name="email"]').value = result.user.email || '';
                }
                // Update localStorage
                const currentUser = JSON.parse(localStorage.getItem('currentUser') || '{}');
                currentUser.name = result.user.name;
                currentUser.email = result.user.email;
                localStorage.setItem('currentUser', JSON.stringify(currentUser));

                // Update button text if needed
                const btnSpan = document.querySelector('.user-icon-btn span');
                if (btnSpan && result.user.email) {
                    btnSpan.innerText = result.user.email.split('@')[0];
                }
            }
        } catch (e) {
            console.error('Error fetching profile:', e);
        }
    };
    window.closeProfileModal = function () {
        document.getElementById('profileModal').classList.remove('active');
        document.getElementById('modalMsg').innerText = '';
    };
    window.switchTab = function (tab) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        event.target.classList.add('active');
        if (tab === 'profile') {
            document.getElementById('profileForm').style.display = 'block';
            document.getElementById('passwordForm').style.display = 'none';
        } else {
            document.getElementById('profileForm').style.display = 'none';
            document.getElementById('passwordForm').style.display = 'block';
        }
    };
    window.logout = function () {
        localStorage.removeItem('currentUser');
        localStorage.removeItem('isAdminLoggedIn');
        window.location.href = '/backend/logout.php'; // Create this or just reload
        // Fallback if logout.php doesn't exist
        setTimeout(() => window.location.reload(), 500);
    };

    window.updateProfile = async function (e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        data.action = 'update_profile';

        await sendRequest(data);
    };

    window.changePassword = async function (e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        data.action = 'change_password';

        if (data.new_password !== data.confirm_password) {
            showMsg('Las contraseñas no coinciden', 'red');
            return;
        }

        await sendRequest(data);
    };

    async function sendRequest(data) {
        const msg = document.getElementById('modalMsg');
        msg.innerText = 'Procesando...';
        msg.style.color = '#94a3b8';

        try {
            const res = await fetch('/backend/profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();

            if (result.success) {
                showMsg(result.message, 'green');
                if (data.action === 'update_profile') {
                    const user = JSON.parse(localStorage.getItem('currentUser'));
                    user.email = data.email;
                    user.name = data.name;
                    localStorage.setItem('currentUser', JSON.stringify(user));
                }
                if (data.action === 'change_password') {
                    e.target.reset();
                }
            } else {
                showMsg(result.message, 'red');
            }
        } catch (err) {
            showMsg('Error de conexión', 'red');
        }
    }

    function showMsg(text, color) {
        const msg = document.getElementById('modalMsg');
        msg.innerText = text;
        msg.style.color = color === 'red' ? '#ef4444' : '#10b981';
    }

    // IMMEDIATE FETCH to ensure data is fresh
    (async function refreshUserData() {
        try {
            const res = await fetch('/backend/profile.php', { credentials: 'include' });
            const result = await res.json();
            if (result.success) {
                const updatedUser = { ...currentUser, ...result.user };
                localStorage.setItem('currentUser', JSON.stringify(updatedUser));

                // Update UI if button exists
                const btnSpan = document.querySelector('.user-icon-btn span');
                if (btnSpan) {
                    btnSpan.innerText = result.user.name || result.user.email.split('@')[0];
                }
            } else {
                console.error('Profile Sync Error:', result);
            }
        } catch (e) {
            console.error('Background profile sync failed', e);
        }
    })();

    // Observer to replace "Ingresar" button
    const observer = new MutationObserver(() => {
        // Find links that contain "Ingresar" or href="/login"
        const links = Array.from(document.querySelectorAll('a'));
        const loginLink = links.find(a => a.textContent.includes('Ingresar') || a.getAttribute('href') === '/login');

        if (loginLink && !loginLink.dataset.patched) {
            loginLink.dataset.patched = 'true';

            // Create User Icon Button
            const userBtn = document.createElement('div');
            userBtn.className = 'user-icon-btn';
            // Use name from localStorage if available, otherwise email
            const displayName = currentUser.name || currentUser.email.split('@')[0];
            userBtn.innerHTML = `<i class="fa-solid fa-user-circle fa-lg"></i> <span>${displayName}</span>`;
            userBtn.onclick = window.openProfileModal;

            // Replace
            loginLink.replaceWith(userBtn);
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Inject Video on Homepage
    function injectHomeVideo() {
        // Only on homepage
        if (window.location.pathname !== '/' && window.location.pathname !== '/index.html') return;

        // Check if already injected
        if (document.getElementById('home-intro-video')) return;

        // Look for the category container (the 3 images)
        const images = Array.from(document.querySelectorAll('img'));
        const categoryImg = images.find(img => img.src && (img.src.includes('BodyArmor') || img.src.includes('Tactical') || img.src.includes('Miras')));

        if (categoryImg) {
            // Find the main container of these images
            let container = categoryImg.closest('.grid') || categoryImg.closest('.flex');

            // Go up a few levels to find the section wrapper
            if (container) {
                // We want to insert AFTER this container
                const videoSection = document.createElement('div');
                videoSection.id = 'home-intro-video';
                videoSection.className = 'w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12';
                videoSection.innerHTML = `
                    <div class="relative rounded-2xl overflow-hidden shadow-2xl border border-slate-800">
                        <video class="w-full h-auto" autoplay loop muted playsinline controls>
                            <source src="/video/Intro.mp4" type="video/mp4">
                            Tu navegador no soporta el tag de video.
                        </video>
                        <div class="absolute inset-0 pointer-events-none bg-gradient-to-t from-slate-900/50 to-transparent"></div>
                    </div>
                `;

                // Insert after the container
                if (container.parentElement) {
                    container.parentElement.insertBefore(videoSection, container.nextSibling);
                }
            }
        }
    }

    // Run injection
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectHomeVideo);
    } else {
        injectHomeVideo();
    }
    window.addEventListener('load', injectHomeVideo);

    // Also try periodically for SPA changes
    setInterval(injectHomeVideo, 1000);

})();
