// ===== VARIABLES GLOBALES =====
let currentFilters = {};
let searchTimeout = null;

// ===== FONCTIONS DE RECHERCHE ET FILTRAGE AVANCÉES =====

// Fonction de recherche générique avec debounce
function searchTable(tableId, searchTerm) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const searchLower = searchTerm.toLowerCase();
    
    rows.forEach((row, index) => {
        const text = row.textContent.toLowerCase();
        const isVisible = text.includes(searchLower);
        
        if (isVisible) {
            row.style.display = '';
            row.style.animation = `fadeInUp 0.3s ease-out ${index * 0.05}s`;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Afficher le nombre de résultats
    updateResultsCount(rows, searchTerm);
}

// Fonction de filtrage avancée
function filterTable(filters = {}) {
    const rows = document.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    rows.forEach((row, index) => {
        let shouldShow = true;
        
        // Appliquer tous les filtres
        Object.keys(filters).forEach(filterKey => {
            const filterValue = filters[filterKey];
            if (filterValue && filterValue !== '') {
                const dataValue = row.getAttribute(`data-${filterKey}`);
                if (dataValue && !dataValue.toLowerCase().includes(filterValue.toLowerCase())) {
                    shouldShow = false;
                }
            }
        });
        
        if (shouldShow) {
            row.style.display = '';
            row.style.animation = `fadeInUp 0.3s ease-out ${index * 0.05}s`;
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Afficher le nombre de résultats
    updateResultsCount(rows, '', visibleCount);
}

// Fonction pour mettre à jour le compteur de résultats
function updateResultsCount(rows, searchTerm = '', visibleCount = null) {
    const totalCount = rows.length;
    const actualVisibleCount = visibleCount !== null ? visibleCount : 
        Array.from(rows).filter(row => row.style.display !== 'none').length;
    
    // Créer ou mettre à jour le compteur
    let counter = document.getElementById('results-counter');
    if (!counter) {
        counter = document.createElement('div');
        counter.id = 'results-counter';
        counter.style.cssText = `
            background: linear-gradient(135deg, #4a006f, #3259b3);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            margin: 10px 0;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(74, 0, 111, 0.2);
        `;
        
        const container = document.querySelector('.table-container');
        if (container) {
            container.insertBefore(counter, container.firstChild);
        }
    }
    
    const searchText = searchTerm ? ` pour "${searchTerm}"` : '';
    counter.innerHTML = `
        <i class="fas fa-search"></i>
        ${actualVisibleCount} résultat${actualVisibleCount > 1 ? 's' : ''} sur ${totalCount}${searchText}
    `;
}

// ===== GESTION DES RÔLES ET FILTRAGE AUTOMATIQUE =====

// Fonction pour filtrer automatiquement selon le rôle
function autoFilterByRole() {
    const userRole = document.body.getAttribute('data-user-role');
    const userRegion = document.body.getAttribute('data-user-region');
    const userVille = document.body.getAttribute('data-user-ville');
    
    if (userRole && (userRole === 'ingenieur' || userRole === 'technicien' || userRole === 'charge_compte')) {
        // Filtrer automatiquement par région/ville
        currentFilters.region = userRegion;
        currentFilters.ville = userVille;
        filterTable(currentFilters);
        
        // Afficher un message informatif avec animation
        showInfoMessage(`Affichage filtré pour votre région: ${userRegion}, ville: ${userVille}`, 'info');
    }
}

// Fonction pour afficher un message informatif avec animations
function showInfoMessage(message, type = 'info') {
    const existingMessage = document.querySelector('.info-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    const colors = {
        info: 'linear-gradient(135deg, #4a006f, #3259b3)',
        success: 'linear-gradient(135deg, #28a745, #20c997)',
        warning: 'linear-gradient(135deg, #ffc107, #ffb347)',
        error: 'linear-gradient(135deg, #dc3545, #e74c3c)'
    };
    
    const icons = {
        info: 'fas fa-info-circle',
        success: 'fas fa-check-circle',
        warning: 'fas fa-exclamation-triangle',
        error: 'fas fa-times-circle'
    };
    
    const infoDiv = document.createElement('div');
    infoDiv.className = 'info-message';
    infoDiv.style.cssText = `
        background: ${colors[type]};
        color: white;
        padding: 15px 20px;
        border-radius: 15px;
        margin: 15px 0;
        text-align: center;
        font-weight: 600;
        box-shadow: 0 4px 8px rgba(74, 0, 111, 0.2);
        animation: slideInDown 0.5s ease-out;
        border: 1px solid rgba(255, 255, 255, 0.2);
    `;
    infoDiv.innerHTML = `
        <i class="${icons[type]}"></i> ${message}
    `;
    
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(infoDiv, container.firstChild);
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            if (infoDiv.parentNode) {
                infoDiv.style.animation = 'slideOutUp 0.5s ease-out';
                setTimeout(() => infoDiv.remove(), 500);
            }
        }, 5000);
    }
}

// ===== BARRES DE RECHERCHE DYNAMIQUES AVANCÉES =====

// Fonction pour créer une barre de recherche moderne
function createModernSearchBar(containerId, tableId, placeholder = 'Rechercher...') {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const searchDiv = document.createElement('div');
    searchDiv.className = 'modern-search-container';
    searchDiv.innerHTML = `
        <div style="display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 300px; position: relative;">
                <input type="text" 
                       id="search-${tableId}" 
                       placeholder="${placeholder}" 
                       style="width: 100%; padding: 15px 50px 15px 20px; border: 2px solid rgba(74, 0, 111, 0.1); border-radius: 25px; font-size: 16px; transition: all 0.3s ease; background: #f8f9fa;">
                <i class="fas fa-search" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); color: #4a006f; font-size: 18px;"></i>
                <div class="search-loading" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); display: none;">
                    <div class="loading"></div>
                </div>
            </div>
            <button onclick="clearSearch('${tableId}')" style="padding: 15px 25px; background: linear-gradient(135deg, #dc3545, #c82333); color: white; border: none; border-radius: 25px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2);">
                <i class="fas fa-times"></i> Effacer
            </button>
        </div>
    `;
    
    container.insertBefore(searchDiv, container.firstChild);
    
    // Ajouter l'événement de recherche avec debounce
    const searchInput = document.getElementById(`search-${tableId}`);
    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value;
        
        // Afficher l'indicateur de chargement
        const loading = searchInput.parentNode.querySelector('.search-loading');
        const searchIcon = searchInput.parentNode.querySelector('.fa-search');
        loading.style.display = 'block';
        searchIcon.style.display = 'none';
        
        // Debounce pour améliorer les performances
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchTable(tableId, searchTerm);
            
            // Masquer l'indicateur de chargement
            loading.style.display = 'none';
            searchIcon.style.display = 'block';
        }, 300);
    });
    
    // Effet de focus
    searchInput.addEventListener('focus', () => {
        searchInput.style.borderColor = '#4a006f';
        searchInput.style.boxShadow = '0 4px 8px rgba(74, 0, 111, 0.2)';
        searchInput.style.transform = 'translateY(-2px)';
    });
    
    searchInput.addEventListener('blur', () => {
        searchInput.style.borderColor = 'rgba(74, 0, 111, 0.1)';
        searchInput.style.boxShadow = 'none';
        searchInput.style.transform = 'translateY(0)';
    });
}

// Fonction pour créer des filtres modernes
function createModernFilters(containerId, filterOptions) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const filtersDiv = document.createElement('div');
    filtersDiv.className = 'modern-filters';
    filtersDiv.style.cssText = `
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        align-items: center;
        flex-wrap: wrap;
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 4px 8px rgba(74, 0, 111, 0.1);
        border: 1px solid rgba(74, 0, 111, 0.1);
    `;
    
    let filtersHTML = '<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">';
    
    filterOptions.forEach(filter => {
        filtersHTML += `
            <div style="display: flex; flex-direction: column; gap: 5px;">
                <label style="font-weight: 600; color: #4a006f; font-size: 14px;">${filter.label}</label>
                <select onchange="updateFilter('${filter.key}', this.value)" style="padding: 10px 15px; border: 2px solid rgba(74, 0, 111, 0.1); border-radius: 15px; background: #f8f9fa; transition: all 0.3s ease; min-width: 150px;">
                    <option value="">${filter.placeholder}</option>
                    ${filter.options.map(option => `<option value="${option.value}">${option.label}</option>`).join('')}
                </select>
            </div>
        `;
    });
    
    filtersHTML += `
        <button onclick="clearAllFilters()" style="padding: 10px 20px; background: linear-gradient(135deg, #6c757d, #5a6268); color: white; border: none; border-radius: 15px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; margin-top: 20px;">
            <i class="fas fa-undo"></i> Réinitialiser
        </button>
    </div>`;
    
    filtersDiv.innerHTML = filtersHTML;
    container.insertBefore(filtersDiv, container.firstChild);
}

// Fonction pour mettre à jour les filtres
function updateFilter(key, value) {
    if (value === '') {
        delete currentFilters[key];
    } else {
        currentFilters[key] = value;
    }
    filterTable(currentFilters);
}

// Fonction pour effacer tous les filtres
function clearAllFilters() {
    currentFilters = {};
    filterTable(currentFilters);
    
    // Réinitialiser tous les selects
    document.querySelectorAll('select').forEach(select => {
        select.value = '';
    });
    
    showInfoMessage('Tous les filtres ont été réinitialisés', 'success');
}

// Fonction pour effacer la recherche
function clearSearch(tableId) {
    const searchInput = document.getElementById(`search-${tableId}`);
    if (searchInput) {
        searchInput.value = '';
        searchTable(tableId, '');
        searchInput.focus();
    }
}

// ===== ANIMATIONS ET EFFETS VISUELS =====

// Fonction pour ajouter des animations aux éléments
function addAnimations() {
    // Animation pour les cartes
    const cards = document.querySelectorAll('.stat-card, .chart-card, .nav-menu a');
    cards.forEach((card, index) => {
        card.style.animation = `fadeInUp 0.6s ease-out ${index * 0.1}s`;
    });
    
    // Animation pour les boutons
    const buttons = document.querySelectorAll('.btn-add, .btn-edit, .btn-delete');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', () => {
            button.style.transform = 'translateY(-3px) scale(1.05)';
        });
        
        button.addEventListener('mouseleave', () => {
            button.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Animation pour les liens de navigation
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', () => {
            link.style.transform = 'translateY(-2px) scale(1.02)';
        });
        
        link.addEventListener('mouseleave', () => {
            link.style.transform = 'translateY(0) scale(1)';
        });
    });
}

// Fonction pour créer des effets de hover avancés
function addHoverEffects() {
    // Effet de hover pour les tableaux
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', () => {
            row.style.transform = 'scale(1.01) translateX(5px)';
            row.style.boxShadow = '0 8px 16px rgba(74, 0, 111, 0.15)';
        });
        
        row.addEventListener('mouseleave', () => {
            row.style.transform = 'scale(1) translateX(0)';
            row.style.boxShadow = 'none';
        });
    });
}

// ===== UTILITAIRES =====

// Fonction pour formater les dates
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Fonction pour formater les nombres
function formatNumber(number) {
    return new Intl.NumberFormat('fr-FR').format(number);
}

// Fonction pour valider les formulaires
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#dc3545';
            isValid = false;
        } else {
            input.style.borderColor = 'rgba(74, 0, 111, 0.1)';
        }
    });
    
    return isValid;
}

// ===== INITIALISATION =====

// Fonction d'initialisation principale
function initializeApp() {
    // Ajouter les animations
    addAnimations();
    addHoverEffects();
    
    // Filtrer automatiquement selon le rôle
    autoFilterByRole();
    
    // Initialiser les barres de recherche
    const searchInputs = document.querySelectorAll('#searchInput');
    searchInputs.forEach(input => {
        input.addEventListener('input', (e) => {
            const searchTerm = e.target.value;
            searchTable('tableBody', searchTerm);
        });
    });
    
    // Initialiser les filtres
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', (e) => {
            const filterKey = e.target.id.replace('Filter', '');
            const filterValue = e.target.value;
            updateFilter(filterKey, filterValue);
        });
    });
    
    // Ajouter des effets de loading
    const buttons = document.querySelectorAll('button[type="submit"]');
    buttons.forEach(button => {
        button.addEventListener('click', () => {
            if (validateForm(button.closest('form').id)) {
                button.innerHTML = '<div class="loading"></div> Chargement...';
                button.disabled = true;
            }
        });
    });
}

// Initialiser l'application quand le DOM est chargé
document.addEventListener('DOMContentLoaded', initializeApp);

// ===== ANIMATIONS CSS =====
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideOutUp {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-30px);
        }
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(74, 0, 111, 0.3);
        border-radius: 50%;
        border-top-color: #4a006f;
        animation: spin 1s ease-in-out infinite;
    }
`;
document.head.appendChild(style); 