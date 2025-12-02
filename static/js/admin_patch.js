(function () {
    console.log('Admin/Public Patch Loaded');

    // =========================================
    // 1. GLOBAL PATCHES (Service Worker, Fetch, etc.)
    // =========================================

    // FORCE UNREGISTER SERVICE WORKERS
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function (registrations) {
            for (let registration of registrations) {
                // console.log('Patch: Unregistering Service Worker:', registration);
                registration.unregister();
            }
        });
        if ('caches' in window) {
            caches.keys().then(function (names) {
                for (let name of names) {
                    caches.delete(name);
                }
            });
        }
    }

    // Monkey-patch fetch to ensure requests use the current hostname
    try {
        if (window.fetch && !window.__admin_patch_fetch_wrapped) {
            const _origFetch = window.fetch.bind(window);
            window.fetch = function (input, init) {
                try {
                    let urlStr = null;
                    let originalRequest = null;
                    if (typeof input === 'string') {
                        urlStr = input;
                    } else if (input && input.url) {
                        urlStr = input.url;
                        originalRequest = input;
                    }

                    if (typeof urlStr === 'string') {
                        if (urlStr.startsWith('https://wolfftactical.cl') || urlStr.startsWith('https://www.wolfftactical.cl')) {
                            const newUrl = urlStr.replace(/^https:\/\/(www\.)?wolfftactical\.cl/, window.location.origin);
                            if (originalRequest) {
                                const opts = {};
                                try {
                                    opts.method = originalRequest.method;
                                    opts.headers = originalRequest.headers;
                                    opts.body = originalRequest.body;
                                    opts.mode = originalRequest.mode;
                                    opts.credentials = originalRequest.credentials;
                                    opts.cache = originalRequest.cache;
                                    opts.redirect = originalRequest.redirect;
                                    opts.referrer = originalRequest.referrer;
                                    opts.integrity = originalRequest.integrity;
                                } catch (e) { }
                                return _origFetch(newUrl, opts || init);
                            }
                            return _origFetch(newUrl, init);
                        }
                    }


                    // Intercept Login to force redirect
                    if (urlStr && urlStr.includes('/backend/login.php')) {
                        return _origFetch(input, init).then(response => {
                            const clone = response.clone();
                            clone.json().then(data => {
                                if (data.success) {
                                    console.log('Patch: Login success, forcing redirect to productos.php');
                                    window.location.href = '/backend/productos.php';
                                }
                            }).catch(() => { });
                            return response;
                        });
                    }

                } catch (e) { }
                return _origFetch(input, init);
            };
            window.__admin_patch_fetch_wrapped = true;
        }
    } catch (e) { }

    // Image Error Handling
    window.addEventListener('error', function (e) {
        if (e.target && e.target.tagName === 'IMG') {
            // Prevent infinite loop
            if (e.target.dataset.errorHandled) return;
            e.target.dataset.errorHandled = 'true';

            // If it's a product image, try to hide it or show placeholder
            // But for now, just ensure we don't show broken icon if possible
            if (!e.target.src || e.target.src.includes('undefined') || e.target.src.includes('null')) {
                e.target.style.display = 'none';
            }
        }
    }, true);

    function fixRawHtml(node) {
        if (!node || node.nodeType !== Node.ELEMENT_NODE) return;

        // CRITICAL FIX: NEVER run this on the product list page (Home or /productos)
        // React manages the list grid, and modifying it causes a crash (NotFoundError)
        const path = window.location.pathname;
        if (path === '/' || path === '/productos' || path === '/category' || path === '/search') {
            return;
        }

        // OPTIMIZATION: Only look for specific containers or elements that look like descriptions
        // Avoid touching the main product grid structure which React manages heavily
        const candidates = node.querySelectorAll ? node.querySelectorAll('.product-description, .description, [class*="desc"], p') : [];

        candidates.forEach(el => {
            if (el.dataset.htmlFixed) return;
            if (el.closest('.ql-editor') || el.closest('textarea') || el.contentEditable === 'true') return;

            // Skip if already formatted by server
            if (el.classList.contains('product-description-formatted') || el.dataset.serverFormatted) return;

            // Skip if parent already has formatted content
            if (el.closest('.product-description-formatted')) return;

            const text = el.innerText || '';

            // STRICTER CHECK: Only fix if it DEFINITELY looks like broken HTML
            // Must contain tags AND be longer than a simple string
            if (text.includes('<') && text.includes('>') && (text.includes('<p>') || text.includes('<ul>') || text.includes('<br>') || text.includes('<strong>'))) {

                // Safety checks
                if (el.querySelector('button, input, select, img, form, video, iframe')) return;
                if (el.querySelectorAll(':scope > div').length > 0) return; // Don't touch if it has div children (layout)
                if (text.length < 10) return;
                if (el.tagName === 'CODE' || el.tagName === 'PRE') return;

                try {
                    // console.log('Patch: Fixing HTML for', el);
                    el.innerHTML = text;
                    el.dataset.htmlFixed = 'true';
                    el.classList.add('product-description-formatted');
                } catch (e) {
                    // console.error('Patch: Error fixing HTML', e);
                }
            }
        });
    }

    // =========================================
    // 3. PUBLIC SITE LOGIC (Product Page)
    // =========================================

    function redesignProductPage() {
        if (!window.location.href.includes('/producto/')) return;

        // 1. Remove "Card" look
        const allElements = document.querySelectorAll('p, span, div, h2');
        let modelEl = null;
        for (let el of allElements) {
            if (el.innerText && el.innerText.includes('Modelo:')) {
                modelEl = el;
                break;
            }
        }

        if (modelEl) {
            let parent = modelEl.parentElement;
            for (let i = 0; i < 8; i++) {
                if (!parent) break;
                const style = window.getComputedStyle(parent);
                if (style.backgroundColor.includes('31,') || style.backgroundColor.includes('17,') || style.backgroundColor.includes('32') || parent.classList.contains('bg-gray-800') || parent.classList.contains('bg-slate-800')) {
                    parent.style.background = 'transparent';
                    parent.style.backgroundColor = 'transparent';
                    parent.style.border = 'none';
                    parent.style.boxShadow = 'none';
                    parent.style.padding = '0';
                    parent.classList.remove('card', 'bg-gray-800', 'bg-slate-800', 'border', 'shadow-lg', 'rounded-lg', 'p-6');

                    const descContainer = parent.querySelector('.max-w-2xl, .w-2/3');
                    if (descContainer) {
                        descContainer.classList.remove('max-w-2xl', 'w-2/3');
                        descContainer.classList.add('w-full');
                        descContainer.style.maxWidth = '100%';
                    }
                    break;
                }
                parent = parent.parentElement;
            }
        }

        // 2. Side-by-Side Color/Quantity
        const labels = Array.from(document.querySelectorAll('h3, span, div, label, p'));
        const colorLabel = labels.find(el => el.innerText && el.innerText.trim() === 'COLOR');
        const qtyLabel = labels.find(el => el.innerText && el.innerText.trim() === 'CANTIDAD');

        if (colorLabel && qtyLabel) {
            const colorContainer = colorLabel.parentElement;
            const qtyContainer = qtyLabel.parentElement;

            if (colorContainer && qtyContainer) {
                if (colorContainer.parentElement.classList.contains('product-options-row')) return;

                if (colorContainer.parentElement === qtyContainer.parentElement) {
                    const parent = colorContainer.parentElement;
                    const wrapper = document.createElement('div');
                    wrapper.className = 'product-options-row flex gap-4 w-full';
                    wrapper.style.display = 'flex';
                    wrapper.style.gap = '20px';
                    wrapper.style.width = '100%';
                    wrapper.style.marginTop = '20px';

                    parent.insertBefore(wrapper, colorContainer);
                    wrapper.appendChild(colorContainer);
                    wrapper.appendChild(qtyContainer);

                    colorContainer.style.flex = '1';
                    qtyContainer.style.flex = '1';
                }
            }
        }

        // 3. Expand Description Width
        const descHeaders = Array.from(document.querySelectorAll('h3, div, h4, span'));
        const descHeader = descHeaders.find(el => el.innerText && (el.innerText.trim() === 'DESCRIPCIÓN' || el.innerText.trim() === 'INCLUYE'));

        if (descHeader) {
            // Traverse up to find the container restricting width
            let container = descHeader.parentElement;
            for (let i = 0; i < 5; i++) {
                if (!container) break;
                // Check if it has width classes
                if (container.classList.contains('max-w-2xl') || container.classList.contains('w-2/3') || container.style.maxWidth) {
                    container.classList.remove('max-w-2xl', 'w-2/3', 'max-w-3xl', 'max-w-xl');
                    container.classList.add('w-full');
                    container.style.maxWidth = '100%';
                    container.style.width = '100%';
                    // console.log('Patch: Expanded description width');
                }
                container = container.parentElement;
            }
        }
    }

    // =========================================
    // 4. ADMIN ONLY LOGIC
    // =========================================

    function applyAdminChanges() {
        if (!window.location.href.includes('/admin')) return;

        // Redirects
        const path = window.location.pathname;
        const uglyHeader = Array.from(document.querySelectorAll('h1, h2, h3, div')).find(el =>
            el.textContent && el.textContent.trim() === 'Lista de Productos'
        );

        if (uglyHeader) {
            window.location.replace('/backend/productos.php');
            return;
        }

        if (path.includes('/backend/productos') && !path.includes('.php')) {
            window.location.replace('/backend/productos.php');
            return;
        }

        // Redirect /admin/ or sigue igual /admin to productos.php
        if (path === '/admin/' || path === '/admin' || path === '/backend/' || path === '/backend') {
            window.location.replace('/backend/productos.php');
            return;
        }

        // Hide Model/SKU inputs (ONLY on Add/Edit pages, and SAFER)
        if (!window.location.href.includes('productos.php')) {
            const modelInputs = document.querySelectorAll('input[name="model"], input#model, [name="model"]');
            for (let inp of modelInputs) {
                // Only hide specific small containers
                const container = inp.closest('.mb-4') || inp.closest('.form-group') || inp.closest('.col-span-6');
                if (container) {
                    container.style.display = 'none';
                } else {
                    inp.style.display = 'none';
                    const id = inp.id;
                    if (id) {
                        const label = document.querySelector(`label[for="${id}"]`);
                        if (label) label.style.display = 'none';
                    }
                }
            }

            const labels = document.querySelectorAll('label');
            for (let label of labels) {
                const txt = (label.textContent || '').trim();
                if (/modelo|sku/i.test(txt)) {
                    const container = label.closest('.mb-4') || label.closest('.form-group');
                    if (container) container.style.display = 'none';
                    else label.style.display = 'none';
                }
            }
        }

        // Center Forms
        const pageHeaders = Array.from(document.querySelectorAll('h1, h2, h3'));
        const targetHeader = pageHeaders.find(h =>
            (h.textContent.includes('Agregar Nuevo Producto') || h.textContent.includes('Editar Producto'))
        );

        if (targetHeader) {
            let mainContainer = targetHeader.closest('main') || targetHeader.closest('.p-4') || targetHeader.parentElement.parentElement;
            if (mainContainer) {
                mainContainer.classList.add('admin-centered-layout');
            }
        }

        // Inject Theme
        if (!document.getElementById('admin-patch-theme')) {
            const style = document.createElement('style');
            style.id = 'admin-patch-theme';
            style.textContent = `:root{--tac-bg:#0f1724;--tac-panel:#0b1220;--tac-accent:#1766ff;--tac-accent-2:#0ea5a4;--tac-muted:#9aa4b2;--tac-card:#0b1220;--tac-border:rgba(255,255,255,0.04)}\n            aside, .admin-nav, .admin-sidebar, .sidebar, .panel {background:var(--tac-bg) !important;color:#e6eef8 !important}\n            .admin-centered-layout {background:transparent !important}\n            .input-tactical, .form-control, .form-select {background:transparent !important;border:1px solid var(--tac-border) !important;color:#e6eef8 !important}\n            .product-table, table, .productos-table {width:100% !important}\n            .btn-edit {background:var(--tac-accent) !important;color:white !important}\n            @media (max-width: 1024px){\n                aside {position:relative !important; width:100% !important;}\n                main {margin-left:0 !important;}\n            }\n            `;
            document.head.appendChild(style);
        }

        // Replace Brand
        try {
            const replaceAdminBrand = () => {
                const selectors = ['.admin-brand', '.admin-sidebar h2', 'aside h2', 'h2'];
                selectors.forEach(sel => {
                    const els = Array.from(document.querySelectorAll(sel));
                    els.forEach(el => {
                        const txt = (el.textContent || '').trim();
                        if (!txt) return;
                        if (txt.includes('Panel de Administración')) {
                            if (el.dataset.wolffPatched) return;
                            el.dataset.wolffPatched = '1';
                            el.innerHTML = '<div class="p-0 m-0 flex items-center gap-3"><div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center font-bold text-white">W</div><span class="font-bold text-lg tracking-wide">WOLFF<span class="text-blue-500">TACTICAL</span></span></div>';
                            const aside = el.closest('aside');
                            if (aside) {
                                aside.classList.add('bg-slate-850');
                            }
                        }
                    });
                });
            };
            replaceAdminBrand();
        } catch (err) { }
    }

    // =========================================
    // 5. ORCHESTRATION (Observer)
    // =========================================

    let mutationTimeout;
    const observer = new MutationObserver((mutations) => {
        if (mutationTimeout) clearTimeout(mutationTimeout);
        mutationTimeout = setTimeout(() => {
            mutations.forEach((mutation) => {
                if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach((node) => {
                        fixRawHtml(node);
                    });
                }
                if (mutation.type === 'characterData') {
                    fixRawHtml(mutation.target.parentElement);
                }
            });

            // Execute based on route
            if (window.location.href.includes('/admin')) {
                applyAdminChanges();
            }
            if (window.location.href.includes('/producto/')) {
                redesignProductPage();
            }
        }, 50); // Small delay to let React finish
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true
    });

    // =========================================
    // 6. CUSTOM EMAIL MODAL (SweetAlert2)
    // =========================================

    // Inject SweetAlert2
    if (!document.getElementById('swal2-css')) {
        const link = document.createElement('link');
        link.id = 'swal2-css';
        link.rel = 'stylesheet';
        link.href = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css';
        document.head.appendChild(link);
    }
    if (!document.getElementById('swal2-js')) {
        const script = document.createElement('script');
        script.id = 'swal2-js';
        script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
        document.head.appendChild(script);
    }

    // Intercept Click
    document.addEventListener('click', function (e) {
        // Find if the clicked element or its parent is the "Enviar por correo" button
        let target = e.target;
        let isSendButton = false;

        // Traverse up to 3 levels to find the button
        for (let i = 0; i < 3; i++) {
            if (!target) break;
            if (target.innerText && target.innerText.trim() === 'Enviar por correo') {
                isSendButton = true;
                break;
            }
            target = target.parentElement;
        }

        if (isSendButton) {
            e.preventDefault();
            e.stopPropagation();

            if (typeof Swal === 'undefined') {
                console.error('SweetAlert2 not loaded yet');
                // Fallback to alert if SWAL failed to load
                alert('Por favor espera un momento a que cargue el formulario...');
                return;
            }

            Swal.fire({
                title: 'Enviar Carrito',
                html: `
                    <p style="margin-bottom: 15px; color: #ccc;">Ingresa tus datos para enviar la cotización.</p>
                    <input id="swal-input1" class="swal2-input" placeholder="Tu Email (Requerido)" type="email" style="margin-bottom: 10px;">
                    <input id="swal-input2" class="swal2-input" placeholder="Tu Teléfono (Opcional)" type="tel">
                `,
                background: '#1f2937', // Dark mode bg
                color: '#fff',
                confirmButtonColor: '#2563eb',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Enviar Solicitud',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const email = document.getElementById('swal-input1').value;
                    const phone = document.getElementById('swal-input2').value;
                    if (!email || !email.includes('@')) {
                        Swal.showValidationMessage('Por favor ingresa un email válido');
                        return false;
                    }
                    return { email: email, phone: phone };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const customerEmail = result.value.email;
                    const customerPhone = result.value.phone;

                    Swal.fire({
                        title: 'Enviando...',
                        text: 'Procesando tu solicitud',
                        background: '#1f2937',
                        color: '#fff',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Helper: Scrape DOM
                    function scrapeCartFromDOM() {
                        console.log('Attempting DOM scrape...');
                        const items = [];
                        // Find all elements containing "Cantidad:" which suggests a cart item
                        const candidates = Array.from(document.querySelectorAll('div, p, span, h3, h4, li'))
                            .filter(el => el.innerText && el.innerText.includes('Cantidad:') && el.innerText.includes('$'));

                        candidates.forEach(el => {
                            // Go up to find the container
                            let container = el.closest('li') || el.closest('.card') || el.parentElement.parentElement;
                            if (!container) return;

                            const text = container.innerText;
                            if (items.find(i => i._rawText === text)) return; // Avoid duplicates

                            // Extract Name (first non-empty line or header)
                            let name = 'Producto';
                            const header = container.querySelector('h2, h3, h4, strong');
                            if (header) name = header.innerText.trim();
                            else {
                                const lines = text.split('\n').map(l => l.trim()).filter(l => l);
                                if (lines.length > 0) name = lines[0];
                            }

                            // Extract Quantity
                            const qtyMatch = text.match(/Cantidad:\s*(\d+)/i);
                            const quantity = qtyMatch ? parseInt(qtyMatch[1]) : 1;

                            // Extract Price
                            const priceMatch = text.match(/\$([\d.]+)/);
                            const price = priceMatch ? parseFloat(priceMatch[1].replace(/\./g, '')) : 0;

                            // Extract Color
                            const colorMatch = text.match(/Color:\s*([^\n]+)/i);
                            const color = colorMatch ? colorMatch[1].trim() : '';

                            items.push({ name, quantity, price, color, _rawText: text });
                        });
                        return items;
                    }

                    // Helper: Try LocalStorage
                    function getCartFromLocalStorage() {
                        console.log('Attempting LocalStorage...');
                        const keys = ['cart', 'shoppingCart', 'wolfftactical_cart', 'redux', 'persist:root'];
                        for (const key of keys) {
                            try {
                                const raw = localStorage.getItem(key);
                                if (!raw) continue;

                                let data = JSON.parse(raw);
                                // Handle Redux persist
                                if (key === 'persist:root' && data.cart) {
                                    data = JSON.parse(data.cart);
                                }

                                // Check if it's an array or has items
                                const candidates = Array.isArray(data) ? data : (data.items || data.cartItems || []);
                                if (Array.isArray(candidates) && candidates.length > 0) {
                                    return candidates.map(item => ({
                                        name: item.name || item.title || 'Producto',
                                        quantity: item.quantity || item.qty || 1,
                                        price: item.price || 0,
                                        color: item.color || item.selectedColor || ''
                                    }));
                                }
                            } catch (e) { console.error('LS parse error', key, e); }
                        }
                        return null;
                    }

                    // 1. Try Backend First
                    fetch('/backend/cart.php')
                        .then(res => {
                            if (res.status === 401 || res.status === 403) throw new Error('Unauthorized');
                            return res.json();
                        })
                        .then(data => {
                            if (!data.success || !data.cart) throw new Error('No backend cart');
                            return data.cart.map(item => ({
                                name: item.name,
                                quantity: item.quantity,
                                price: item.price,
                                color: item.options ? (Object.values(item.options)[0] || '') : ''
                            }));
                        })
                        .catch(err => {
                            console.warn('Backend cart failed, trying fallbacks...', err);
                            // 2. Try LocalStorage
                            let items = getCartFromLocalStorage();

                            // 3. Try DOM Scrape
                            if (!items || items.length === 0) {
                                items = scrapeCartFromDOM();
                            }

                            if (!items || items.length === 0) {
                                throw new Error('No se pudo obtener el carrito (vacío o error)');
                            }
                            return items;
                        })
                        .then(items => {
                            console.log('Sending items:', items);
                            // 4. Send Email
                            return fetch('/backend/send_cart_email.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    customer_email: customerEmail,
                                    customer_phone: customerPhone,
                                    items: items
                                })
                            });
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: '¡Enviado!',
                                    text: 'Tu carrito ha sido enviado exitosamente.',
                                    icon: 'success',
                                    background: '#1f2937',
                                    color: '#fff',
                                    confirmButtonColor: '#2563eb'
                                });
                            } else {
                                throw new Error(data.message || 'Error desconocido');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            Swal.fire({
                                title: 'Error',
                                text: 'Hubo un problema al enviar: ' + err.message,
                                icon: 'error',
                                background: '#1f2937',
                                color: '#fff'
                            });
                        });
                }
            });
        }
    }, true); // Capture phase

    // Initial Run - Execute when DOM is ready
    function runInitialProcessing() {
        fixRawHtml(document.body);

        if (window.location.href.includes('/producto/')) redesignProductPage();
        if (window.location.href.includes('/admin')) {
            setInterval(applyAdminChanges, 500);
        }
    }

    // Run processing as soon as possible
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runInitialProcessing);
    } else {
        runInitialProcessing();
    }

    // Also run on window load for safety
    window.addEventListener('load', runInitialProcessing);

})();
