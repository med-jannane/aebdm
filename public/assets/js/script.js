document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('meta[name="viewport"]')) {
        const viewportMeta = document.createElement('meta');
        viewportMeta.name = 'viewport';
        viewportMeta.content = 'width=device-width, initial-scale=1.0';
        document.head.appendChild(viewportMeta);
    }

    document.body.classList.add('premium-ui');

    const getSidebar = () => document.getElementById('sidebar') || document.querySelector('.sidebar');
    const getOverlay = () => document.getElementById('sidebarOverlay') || document.querySelector('.sidebar-overlay');
    const getHeader = () => document.querySelector('header');

    const closeSidebar = () => {
        const sidebar = getSidebar();
        const overlay = getOverlay();
        if (sidebar) sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
    };

    const openSidebar = () => {
        const sidebar = getSidebar();
        const overlay = getOverlay();
        if (sidebar) sidebar.classList.add('active');
        if (overlay) overlay.classList.add('active');
    };

    const enhanceHeader = () => {
        const header = getHeader();
        if (!header || header.querySelector('.header-tools')) return;

        const actions = header.querySelector('.hdr-actions') || document.createElement('div');
        actions.classList.add('header-tools');

        if (!actions.querySelector('.header-search')) {
            const search = document.createElement('input');
            search.type = 'search';
            search.className = 'header-search';
            search.placeholder = 'Rechercher tickets, clients, contrats...';
            actions.prepend(search);
        }

        if (!header.contains(actions)) {
            header.appendChild(actions);
        }
    };

    const decorateSidebar = () => {
        const sidebar = getSidebar();
        if (!sidebar) return;

        const title = sidebar.querySelector('h2');
        if (title && title.querySelector('i') && !title.querySelector('span')) {
            const text = title.textContent.trim();
            title.innerHTML = `<i class="fa-solid fa-layer-group"></i><span>${text}</span>`;
        }
    };

    const normalizeTables = () => {
        document.querySelectorAll('table').forEach((table) => {
            const headerCells = table.querySelectorAll('thead th');
            if (!headerCells.length) return;

            table.classList.add('mobile-stack');
            const labels = Array.from(headerCells).map((th) => th.textContent.trim() || 'Champ');

            table.querySelectorAll('tbody tr').forEach((row) => {
                row.querySelectorAll('td').forEach((td, index) => {
                    if (!td.dataset.label) {
                        td.dataset.label = labels[index] || 'Champ';
                    }
                });
            });
        });
    };

    const normalizeForms = () => {
        const candidates = new Set();

        document.querySelectorAll('form').forEach((form) => {
            if (form.querySelectorAll(':scope > .form-group').length >= 2) {
                candidates.add(form);
            }

            form.querySelectorAll('div, section, fieldset').forEach((container) => {
                const directGroups = container.querySelectorAll(':scope > .form-group, :scope > .field, :scope > .input-group');
                if (directGroups.length >= 2 && !container.classList.contains('form-grid')) {
                    candidates.add(container);
                }
            });
        });

        candidates.forEach((container) => {
            container.classList.add('auto-form-grid');
            container.querySelectorAll(':scope > .form-group, :scope > .field, :scope > .input-group').forEach((group) => {
                const hasLongField = group.querySelector('textarea, table, .table-wrapper, .btn-full, [type="file"]');
                if (hasLongField) group.classList.add('auto-span-2');
            });
        });
    };

    const normalizeModals = () => {
        document.querySelectorAll('.modal').forEach((modal) => {
            const content = modal.querySelector('.modal-content');
            if (!content) return;

            if (!content.querySelector('.modal-body')) {
                const header = content.querySelector('.modal-header');
                const footer = content.querySelector('.modal-footer');
                const body = document.createElement('div');
                body.className = 'modal-body';
                const footerAnchor = footer && footer.parentNode === content ? footer : null;

                Array.from(content.children).forEach((child) => {
                    if (child !== header && child !== footer) {
                        body.appendChild(child);
                    }
                });

                if (header) {
                    content.insertBefore(body, footerAnchor);
                } else {
                    content.prepend(body);
                }
            }
        });
    };

    const initThemeToggle = () => {
        const toggle = document.getElementById('themeToggle');
        if (!toggle) return;

        const root = document.documentElement;
        const updateIcon = (theme) => {
            const icon = toggle.querySelector('i');
            if (icon) icon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
        };

        const savedTheme = localStorage.getItem('theme') || 'light';
        root.setAttribute('data-theme', savedTheme);
        toggle.classList.toggle('dark', savedTheme === 'dark');
        updateIcon(savedTheme);

        toggle.addEventListener('click', () => {
            const current = root.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            toggle.classList.toggle('dark', next === 'dark');
            updateIcon(next);
        });
    };

    const initNotifications = () => {
        const notifBtn = document.getElementById('notif-btn');
        const notifDropdown = document.getElementById('notif-dropdown');
        const notifList = document.getElementById('notif-list');
        const notifBadge = document.getElementById('notif-badge');
        const markAllReadBtn = document.getElementById('mark-all-read');

        if (!notifBtn || !notifDropdown || !notifList || !notifBadge || !markAllReadBtn) return;

        const path = window.location.pathname || '';
        const srcIndex = path.indexOf('/src/');
        const publicIndex = path.indexOf('/public/');
        const appRoot = srcIndex !== -1
            ? path.slice(0, srcIndex)
            : (publicIndex !== -1 ? path.slice(0, publicIndex) : '');
        const apiBase = `${window.location.origin}${appRoot}/public/api`;
        let lastUnreadCount = null;

        const formatDate = (rawDate) => {
            if (!rawDate) return '';
            const parsed = new Date(rawDate.replace(' ', 'T'));
            if (Number.isNaN(parsed.getTime())) return rawDate;
            return parsed.toLocaleString('fr-FR', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
            });
        };

        const escapeHtml = (value) => {
            const div = document.createElement('div');
            div.textContent = String(value || '');
            return div.innerHTML;
        };

        const normalizeLink = (link) => {
            if (!link || typeof link !== 'string') return '';
            const raw = link.trim();
            if (!raw) return '';
            if (/^https?:\/\//i.test(raw)) return raw;
            if (raw.startsWith('/')) return `${window.location.origin}${appRoot}${raw}`;
            const normalizedRelative = raw.startsWith('./') ? raw.slice(2) : raw;
            return `${window.location.origin}${appRoot}/${normalizedRelative}`;
        };

        const updateUI = (notifications, unreadCount) => {
            const count = Number(unreadCount || 0);
            notifBadge.textContent = String(count);
            notifBadge.classList.toggle('hidden', count <= 0);

            if (lastUnreadCount !== null && count > lastUnreadCount) {
                notifBtn.classList.add('has-new');
                setTimeout(() => notifBtn.classList.remove('has-new'), 1000);
            }
            lastUnreadCount = count;

            notifList.innerHTML = '';
            if (!notifications.length) {
                notifList.innerHTML = '<li class="empty-notif">Aucune notification</li>';
                return;
            }

            notifications.forEach((n) => {
                const li = document.createElement('li');
                const isRead = Number(n.is_read || 0) === 1;
                li.className = `notif-item ${isRead ? 'is-read' : 'is-unread'}`;
                li.innerHTML = `
                    <div class="notif-content">
                        <p>${escapeHtml(n.message || '')}</p>
                        <span class="notif-date">${formatDate(n.created_at)}</span>
                    </div>
                `;
                li.addEventListener('click', () => markReadAndNavigate(n.id, n.link));
                notifList.appendChild(li);
            });
        };

        const loadNotifications = () => {
            fetch(`${apiBase}/get_notifications.php`)
                .then((response) => {
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    return response.json();
                })
                .then((data) => updateUI(data.notifications || [], data.unread_count || 0))
                .catch((err) => console.error('Error loading notifications:', err));
        };

        const markReadAndNavigate = (id, link) => {
            const targetLink = normalizeLink(link);
            fetch(`${apiBase}/mark_read.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            })
                .then(() => {
                    if (targetLink) {
                        window.location.href = targetLink;
                    } else {
                        loadNotifications();
                    }
                })
                .catch(() => {
                    if (targetLink) window.location.href = targetLink;
                });
        };

        notifBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            notifDropdown.classList.toggle('open');
        });

        notifDropdown.addEventListener('click', (event) => event.stopPropagation());
        document.addEventListener('click', () => notifDropdown.classList.remove('open'));

        markAllReadBtn.addEventListener('click', (event) => {
            event.preventDefault();
            fetch(`${apiBase}/mark_read.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: 'all' })
            }).then(loadNotifications).catch((err) => console.error('Error marking notifications as read:', err));
        });

        loadNotifications();
        setInterval(loadNotifications, 5000);
    };

    const initCharts = () => {
        if (typeof window.Chart === 'undefined') return;

        const existing = document.querySelector('[data-chart-mounted="1"]');
        const kpiGrid = document.querySelector('.kpi-grid');
        const pageContent = document.querySelector('.page-content');

        if (!existing && kpiGrid && pageContent) {
            const wrapper = document.createElement('section');
            wrapper.className = 'grid-2 chart-grid';
            wrapper.dataset.chartMounted = '1';
            wrapper.innerHTML = `
                <article class="chart-wrap">
                    <div class="sec-head"><h2>Performance Mensuelle</h2></div>
                    <canvas id="premiumBarChart" height="120"></canvas>
                </article>
                <article class="chart-wrap">
                    <div class="sec-head"><h2>Repartition Tickets</h2></div>
                    <canvas id="premiumDonutChart" height="120"></canvas>
                </article>
            `;
            pageContent.appendChild(wrapper);
        }

        const barCanvas = document.getElementById('premiumBarChart');
        if (barCanvas && !barCanvas.dataset.ready) {
            barCanvas.dataset.ready = '1';
            new Chart(barCanvas, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Jun'],
                    datasets: [
                        {
                            label: 'Tickets',
                            data: [12, 19, 15, 22, 16, 25],
                            backgroundColor: '#4F46E5',
                            borderRadius: 8,
                            borderSkipped: false
                        },
                        {
                            label: 'Resolus',
                            data: [8, 15, 11, 19, 13, 22],
                            backgroundColor: '#D1D5DB',
                            borderRadius: 8,
                            borderSkipped: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { grid: { color: '#EEF2F7' }, beginAtZero: true }
                    }
                }
            });
        }

        const donutCanvas = document.getElementById('premiumDonutChart');
        if (donutCanvas && !donutCanvas.dataset.ready) {
            donutCanvas.dataset.ready = '1';
            new Chart(donutCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Ouverts', 'TAC', 'Planifies', 'Clotures'],
                    datasets: [{
                        data: [28, 24, 22, 26],
                        backgroundColor: ['#4F46E5', '#22C55E', '#F59E0B', '#EF4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: { legend: { position: 'right' } }
                }
            });
        }
    };

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('.mobile-toggle');
        if (toggle) {
            event.preventDefault();
            event.stopPropagation();
            const sidebar = getSidebar();
            if (!sidebar) return;
            if (sidebar.classList.contains('active')) {
                closeSidebar();
            } else {
                openSidebar();
            }
            return;
        }

        const overlay = getOverlay();
        if (overlay && event.target === overlay) {
            event.preventDefault();
            closeSidebar();
            return;
        }

        const closeBtn = event.target.closest('.modal .close, .modal [data-close-modal]');
        if (closeBtn) {
            const modal = closeBtn.closest('.modal');
            if (modal) modal.style.display = 'none';
            return;
        }

        if (event.target.classList?.contains('modal')) {
            event.target.style.display = 'none';
        }
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeSidebar();
        document.querySelectorAll('.modal').forEach((modal) => {
            if (modal.style.display !== 'none') {
                modal.style.display = 'none';
            }
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });

    enhanceHeader();
    decorateSidebar();
    normalizeTables();
    normalizeForms();
    normalizeModals();
    initThemeToggle();
    initNotifications();
    initCharts();
});
