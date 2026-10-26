const API = 'api.php';
let csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let currentUser = null;
let currentCategory = 'Semua';
let favoritesOnly = false;
let historyOnly = false;
let searchTimer;
const pageMode = document.body.dataset.pageMode || 'home';
const recipeGrid = document.getElementById('recipe-grid');
const recipeDialog = document.getElementById('recipe-dialog');
const toast = document.getElementById('toast');

async function apiGet(params) {
    const response = await fetch(`${API}?${new URLSearchParams(params)}`, { headers: { Accept: 'application/json' } });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Permintaan gagal.');
    return data;
}

async function apiPost(payload) {
    const response = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await response.json();
    if (data.csrf) {
        csrfToken = data.csrf;
        document.querySelector('meta[name="csrf-token"]').content = csrfToken;
    }
    if (!response.ok) throw new Error(data.error || 'Permintaan gagal.');
    return data;
}

function escapeHtml(value = '') {
    return String(value).replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[character]);
}

let toastTimer;
function showToast(message) {
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
}

function recipeImage(recipe) {
    const imagePath = String(recipe.image || '');
    return /^uploads\/recipes\/[a-zA-Z0-9_-]+\.(?:jpe?g|png|webp)$/i.test(imagePath)
        ? imagePath
        : 'assets/images/recipe-art.svg';
}

function recipeCard(recipe) {
    const tags = (recipe.tags || []).filter(tag => tag !== 'Lokal Indonesia').slice(0, 2)
        .map(tag => `<span class="tag">${escapeHtml(tag)}</span>`).join('');
    return `<article class="recipe-card" data-recipe-id="${Number(recipe.id)}" tabindex="0" role="button" aria-label="Lihat resep ${escapeHtml(recipe.name)}">
        <div class="card-image" style="background-image:url('${escapeHtml(recipeImage(recipe))}')">
            <span class="duration-badge">◷ ${Number(recipe.duration)} menit</span>
            <button class="favorite-toggle ${recipe.is_favorite ? 'selected' : ''}" data-favorite-id="${Number(recipe.id)}" aria-label="${recipe.is_favorite ? 'Hapus dari' : 'Tambahkan ke'} favorit">${recipe.is_favorite ? '♥' : '♡'}</button>
        </div>
        <div class="card-body"><h3>${escapeHtml(recipe.name)}</h3><p>${escapeHtml(recipe.description)}</p><div class="tags">${tags}</div></div>
    </article>`;
}

function renderRecommendations(recipes) {
    const recommendationList = document.getElementById('recommendation-list');
    recommendationList.innerHTML = recipes.slice(0, 3).map(recipe => `<article class="recommendation" data-recipe-id="${Number(recipe.id)}" tabindex="0" role="button">
        <img src="${escapeHtml(recipeImage(recipe))}" alt="${escapeHtml(recipe.name)}" loading="lazy">
        <div><h3>${escapeHtml(recipe.name)}</h3><small>${Number(recipe.duration)} menit · ${escapeHtml(recipe.difficulty)}</small></div>
    </article>`).join('');
}

async function loadCategories() {
    try {
        const data = await apiGet({ action: 'categories' });
        const categories = [{ name: 'Semua', icon: '✦' }, ...data.categories];
        document.getElementById('category-list').innerHTML = categories.map(category =>
            `<button type="button" class="category-chip${category.name === currentCategory ? ' active' : ''}" data-category="${escapeHtml(category.name)}">${escapeHtml(category.icon)} ${escapeHtml(category.name)}</button>`
        ).join('');
    } catch (error) {
        showToast(error.message);
    }
}

async function loadRecipes() {
    recipeGrid.innerHTML = '<div class="loading-state">Memuat resep...</div>';
    const params = { action: 'list' };
    const search = document.getElementById('header-search').value.trim();
    if (search) params.q = search;
    if (currentCategory !== 'Semua') params.category = currentCategory;
    if (favoritesOnly) params.favorites = '1';
    try {
        const data = await apiGet(params);
        let recipes = data.recipes || [];
        if (historyOnly) {
            const history = JSON.parse(localStorage.getItem('resepin-history') || '[]');
            const position = new Map(history.map((id, index) => [Number(id), index]));
            recipes = recipes.filter(recipe => position.has(Number(recipe.id)))
                .sort((first, second) => position.get(Number(first.id)) - position.get(Number(second.id)));
        }
        if (pageMode === 'recommendations' && !search) {
            recipes = recipes.filter(recipe => Number(recipe.featured) === 1);
        }
        document.getElementById('recipe-heading').textContent = historyOnly ? 'Riwayat Resep'
            : favoritesOnly ? 'Resep Favorit'
                : pageMode === 'recommendations' ? 'Rekomendasi Resep'
                    : search ? `Hasil: ${search}` : 'Resep Nusantara';
        document.getElementById('result-count').textContent = `${recipes.length} resep ditemukan`;
        recipeGrid.innerHTML = recipes.length
            ? recipes.map(recipeCard).join('')
            : '<div class="empty-state">Belum ada resep yang cocok. Coba kata kunci lain.</div>';
        renderRecommendations(data.recipes || []);
    } catch (error) {
        recipeGrid.innerHTML = `<div class="empty-state">${escapeHtml(error.message)}<br><br>Pastikan Apache, MySQL, dan database Resepin aktif.</div>`;
    }
}

function rememberRecipe(id) {
    const history = JSON.parse(localStorage.getItem('resepin-history') || '[]')
        .filter(existing => Number(existing) !== Number(id));
    history.unshift(Number(id));
    localStorage.setItem('resepin-history', JSON.stringify(history.slice(0, 30)));
}

async function openRecipe(id) {
    try {
        const result = await apiGet({ action: 'detail', id });
        const recipe = result.recipe;
        rememberRecipe(recipe.id);
        const ingredients = (recipe.ingredients || []).map(item =>
            `<li><span>${escapeHtml(item.name)}</span><b>${escapeHtml(item.amount)}</b></li>`).join('');
        const instructions = (recipe.instructions || []).map(step => `<li>${escapeHtml(step)}</li>`).join('');
        document.getElementById('recipe-detail').innerHTML = `
            <img class="detail-hero" src="${escapeHtml(recipeImage(recipe))}" alt="${escapeHtml(recipe.name)}">
            <div class="detail-content">
                <span class="eyebrow">${escapeHtml(recipe.category)}</span>
                <h2>${escapeHtml(recipe.name)}</h2><p>${escapeHtml(recipe.description)}</p>
                <p class="recipe-meta">${Number(recipe.duration)} menit · ${escapeHtml(recipe.difficulty)} · ${Number(recipe.servings)} porsi</p>
                <h3>Bahan-bahan</h3><ul class="ingredient-list">${ingredients}</ul>
                <h3>Langkah pembuatan</h3><ol class="instruction-list">${instructions}</ol>
            </div>`;
        recipeDialog.showModal();
    } catch (error) {
        showToast(error.message);
    }
}

async function toggleFavorite(id) {
    try {
        const result = await apiPost({ action: 'favorite', id });
        showToast(result.is_favorite ? 'Resep ditambahkan ke favorit.' : 'Resep dihapus dari favorit.');
        await loadRecipes();
    } catch (error) {
        showToast(error.message);
    }
}

function appendChatMessage(message, isUser) {
    const node = document.createElement('div');
    node.className = `chat-message${isUser ? ' user' : ''}`;
    node.textContent = message;
    document.getElementById('chat-messages').append(node);
    node.scrollIntoView({ block: 'nearest' });
}

async function sendQuestion(message) {
    const question = message.trim();
    if (!question) return;
    appendChatMessage(question, true);
    try {
        const result = await apiPost({ action: 'assistant', message: question });
        appendChatMessage(result.answer, false);
        (result.recipes || []).forEach(recipe => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'chat-recipe-card';
            card.dataset.recipeId = recipe.id;
            const image = document.createElement('img');
            image.src = recipeImage(recipe);
            image.alt = '';
            const details = document.createElement('span');
            details.className = 'chat-recipe-details';
            details.innerHTML = `<strong>${escapeHtml(recipe.name)}</strong><small>${Number(recipe.duration)} menit · lihat resep</small>`;
            card.append(image, details);
            document.getElementById('chat-messages').append(card);
        });
    } catch (error) {
        appendChatMessage(error.message, false);
    }
}

function renderAuthForm(type = 'login') {
    const isRegister = type === 'register';
    document.getElementById('auth-content').innerHTML = `<span class="eyebrow">AKUN PENGGUNA RESEPIN</span>
        <h2>${isRegister ? 'Daftar akun pengguna' : 'Masuk ke akun pengguna'}</h2>
        <div class="auth-tabs" role="tablist" aria-label="Pilih masuk atau daftar">
            <button type="button" class="auth-tab${isRegister ? '' : ' active'}" data-auth-tab="login" role="tab" aria-selected="${!isRegister}">Masuk</button>
            <button type="button" class="auth-tab${isRegister ? ' active' : ''}" data-auth-tab="register" role="tab" aria-selected="${isRegister}">Daftar</button>
        </div>
        <p class="auth-role-note">Akun baru otomatis menjadi pengguna. Hanya admin yang bisa menambah, mengedit, dan menghapus resep.</p>
        <form id="auth-form" class="auth-form">
            ${isRegister ? '<label>Nama lengkap<input name="name" maxlength="80" autocomplete="name" required></label>' : ''}
            <label>Email<input name="email" type="email" maxlength="190" autocomplete="email" required></label>
            <label>Kata sandi<input name="password" type="password" minlength="8" maxlength="72" autocomplete="${isRegister ? 'new-password' : 'current-password'}" required></label>
            <button class="primary-button" type="submit">${isRegister ? 'Daftar sebagai pengguna' : 'Masuk sebagai pengguna'}</button>
        </form>
        <p class="auth-admin-separate">Admin? <a href="admin/login.php">Masuk melalui halaman admin</a></p>`;
    document.querySelectorAll('[data-auth-tab]').forEach(button => {
        button.addEventListener('click', () => renderAuthForm(button.dataset.authTab));
    });
    document.getElementById('auth-form').addEventListener('submit', async event => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        try {
            const result = await apiPost({ action: 'auth', type, ...Object.fromEntries(form.entries()) });
            currentUser = result.user;
            updateProfileButton();
            document.getElementById('auth-dialog').close();
            showToast(result.message);
            if (currentUser.role === 'admin') window.location.href = 'admin/dashboard.php';
        } catch (error) {
            showToast(error.message);
        }
    });
}

function openAccount() {
    const content = document.getElementById('auth-content');
    if (!currentUser) {
        renderAuthForm(document.body.dataset.authMode === 'register' ? 'register' : 'login');
    } else {
        const roleName = currentUser.role === 'admin' ? 'ADMIN' : 'PENGGUNA';
        const admin = currentUser.role === 'admin' ? '<a class="primary-button account-admin-link" href="admin/dashboard.php">Kelola resep</a>' : '';
        content.innerHTML = `<span class="eyebrow">AKUN ${roleName}</span><h2>Halo, ${escapeHtml(currentUser.name)}!</h2>
            <p>Email: ${escapeHtml(currentUser.email)}</p><p>Favorit disimpan pada sesi browser ini.</p>${admin}
            <button class="primary-button" id="logout-button" type="button">Keluar dari akun</button>`;
        document.getElementById('logout-button').addEventListener('click', async () => {
            try {
                await apiPost({ action: 'auth', type: 'logout' });
                currentUser = null;
                updateProfileButton();
                document.getElementById('auth-dialog').close();
                showToast('Kamu sudah keluar.');
            } catch (error) {
                showToast(error.message);
            }
        });
    }
    document.getElementById('auth-dialog').showModal();
}

function updateProfileButton() {
    const button = document.getElementById('profile-button');
    button.textContent = currentUser ? currentUser.name.trim().charAt(0).toUpperCase() : '♙';
    button.title = currentUser ? `Akun ${currentUser.name}` : 'Masuk ke akun pengguna';
    const loginLink = document.querySelector('.header-auth-link');
    if (loginLink) loginLink.hidden = Boolean(currentUser);
    const registerLink = document.querySelector('.header-register-link');
    if (registerLink) registerLink.hidden = Boolean(currentUser);
}

document.addEventListener('click', event => {
    const favoriteButton = event.target.closest('[data-favorite-id]');
    if (favoriteButton) {
        event.preventDefault();
        event.stopPropagation();
        toggleFavorite(Number(favoriteButton.dataset.favoriteId));
        return;
    }
    const category = event.target.closest('[data-category]');
    if (category) {
        currentCategory = category.dataset.category;
        favoritesOnly = false;
        historyOnly = false;
        document.querySelectorAll('.category-chip').forEach(chip => chip.classList.toggle('active', chip === category));
        loadRecipes();
    }
    const recipe = event.target.closest('[data-recipe-id]');
    if (recipe) openRecipe(Number(recipe.dataset.recipeId));
    if (event.target.closest('[data-open-guide]')) document.getElementById('guide-dialog').showModal();
    if (event.target.closest('[data-show-favorites]')) {
        favoritesOnly = true;
        historyOnly = false;
        loadRecipes();
    }
    if (event.target.closest('#show-history')) {
        favoritesOnly = false;
        historyOnly = true;
        loadRecipes();
    }
    if (event.target.closest('[data-close-dialog]')) event.target.closest('dialog').close();
});

document.addEventListener('keydown', event => {
    if (event.key === 'Enter' && event.target.matches('[data-recipe-id]')) {
        openRecipe(Number(event.target.dataset.recipeId));
    }
});

document.getElementById('search-form').addEventListener('submit', event => {
    event.preventDefault();
    const query = document.getElementById('header-search').value.trim();
    window.location.href = `pages/cari-resep.php${query ? `?q=${encodeURIComponent(query)}` : ''}`;
});
document.getElementById('header-search').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        favoritesOnly = false;
        historyOnly = false;
        loadRecipes();
    }, 200);
});
document.getElementById('profile-button').addEventListener('click', openAccount);
document.getElementById('hero-chat-form')?.addEventListener('submit', event => {
    event.preventDefault();
    const input = document.getElementById('hero-chat-input');
    document.getElementById('chat-input').value = input.value;
    sendQuestion(input.value);
    input.value = '';
});
document.getElementById('chat-form').addEventListener('submit', event => {
    event.preventDefault();
    const input = document.getElementById('chat-input');
    sendQuestion(input.value);
    input.value = '';
});
document.querySelectorAll('[data-prompt]').forEach(button => button.addEventListener('click', () => {
    document.getElementById('hero-chat-input').value = button.dataset.prompt;
    document.getElementById('hero-chat-form').requestSubmit();
}));
document.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('click', event => {
    if (event.target === dialog) dialog.close();
}));

async function initialize() {
    try {
        const session = await apiGet({ action: 'session' });
        currentUser = session.user;
        if (session.csrf) {
            csrfToken = session.csrf;
            document.querySelector('meta[name="csrf-token"]').content = csrfToken;
        }
        updateProfileButton();
    } catch (error) {
        showToast(error.message);
    }
    if (pageMode === 'account') openAccount();
    await Promise.all([loadCategories(), loadRecipes()]);
    const initialId = Number(document.body.dataset.recipeId);
    if (initialId > 0) openRecipe(initialId);
    if (pageMode === 'search' && document.getElementById('header-search').value) {
        document.getElementById('recipes').scrollIntoView({ behavior: 'smooth' });
    }
}

initialize();
