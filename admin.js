const adminApi = '../api.php';
const adminCsrfToken = document.querySelector('meta[name="csrf-token"]').content;
const recipeForm = document.getElementById('recipe-form');
const recipeList = document.getElementById('admin-recipe-list');
const adminAccountForm = document.getElementById('admin-account-form');
const editorPanel = document.getElementById('editor-panel');
const deleteDialog = document.getElementById('delete-dialog');
const imageInput = document.getElementById('recipe-image');
const imagePreview = document.getElementById('image-preview');
const toast = document.getElementById('admin-toast');
let recipes = [];
let selectedDeleteId = null;
let previewUrl = null;
let toastTimer;

async function loadAdminAccounts() {
    const list = document.getElementById('admin-account-list');
    try {
        const result = await requestJson(`${adminApi}?action=admin_users`);
        list.innerHTML = (result.users || []).map(user =>
            `<div class="admin-account-row"><span>👤</span><div><b>${escapeHtml(user.name)}</b><small>${escapeHtml(user.email)}</small></div></div>`
        ).join('');
    } catch (error) {
        list.innerHTML = `<p class="form-error">${escapeHtml(error.message)}</p>`;
    }
}

async function requestJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', ...(options.headers || {}) },
        ...options
    });
    let result;
    try {
        result = await response.json();
    } catch (error) {
        throw new Error('Server memberikan respons yang tidak dapat dibaca.');
    }
    if (!response.ok) {
        throw new Error(result.error || 'Permintaan gagal. Coba lagi.');
    }
    return result;
}

function escapeHtml(value = '') {
    return String(value).replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[character]);
}

function showToast(message) {
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
}

adminAccountForm.addEventListener('submit', async event => {
    event.preventDefault();
    const button = adminAccountForm.querySelector('button');
    const errorOutput = document.getElementById('admin-account-error');
    button.disabled = true;
    errorOutput.textContent = '';
    try {
        const values = Object.fromEntries(new FormData(adminAccountForm).entries());
        const result = await requestJson(adminApi, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': adminCsrfToken
            },
            body: JSON.stringify({ action: 'admin_user_create', ...values })
        });
        adminAccountForm.reset();
        showToast(result.message || 'Akun admin berhasil dibuat.');
        await loadAdminAccounts();
    } catch (error) {
        errorOutput.textContent = error.message;
    } finally {
        button.disabled = false;
    }
});

function showEditor() {
    editorPanel.hidden = false;
    editorPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function clearImagePreview() {
    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    }
}

function setImagePreview(source, label) {
    clearImagePreview();
    const safeSource = source && !source.startsWith('data:') && !source.startsWith('blob:')
        ? (source.startsWith('uploads/recipes/') ? `../${source}` : '../assets/images/recipe-art.svg')
        : source;
    imagePreview.innerHTML = source
        ? `<img src="${escapeHtml(safeSource)}" alt="Pratinjau gambar resep"><small>${escapeHtml(label)}</small>`
        : '<span>🧁</span><small>Pratinjau gambar kue</small>';
}

function resetForm() {
    recipeForm.reset();
    recipeForm.elements.id.value = '';
    recipeForm.elements.duration.value = '45';
    recipeForm.elements.servings.value = '8';
    document.getElementById('editor-title').textContent = 'Tambah resep baru';
    document.getElementById('save-recipe').textContent = 'Simpan resep';
    document.getElementById('image-picker-label').textContent = 'Pilih / edit gambar';
    document.getElementById('form-error').textContent = '';
    setImagePreview('', '');
}

function setFormFromRecipe(recipe) {
    recipeForm.elements.id.value = recipe.id;
    recipeForm.elements.name.value = recipe.name || '';
    recipeForm.elements.description.value = recipe.description || '';
    recipeForm.elements.category.value = recipe.category || '';
    recipeForm.elements.duration.value = recipe.duration || 45;
    recipeForm.elements.servings.value = recipe.servings || 8;
    recipeForm.elements.difficulty.value = recipe.difficulty || 'Mudah';
    recipeForm.elements.tags.value = (recipe.tags || []).filter(tag => tag !== 'Lokal Indonesia').join(', ');
    recipeForm.elements.featured.checked = Boolean(Number(recipe.featured));
    recipeForm.elements.ingredients.value = (recipe.ingredients || [])
        .map(ingredient => `${ingredient.amount || ''} | ${ingredient.name || ''}`)
        .join('\n');
    recipeForm.elements.instructions.value = (recipe.instructions || []).join('\n');
    imageInput.value = '';
    document.getElementById('editor-title').textContent = 'Edit resep';
    document.getElementById('save-recipe').textContent = 'Simpan perubahan';
    document.getElementById('image-picker-label').textContent = 'Pilih gambar baru';
    document.getElementById('form-error').textContent = '';
    setImagePreview(recipe.image || '', 'Gambar saat ini · pilih file untuk mengganti');
}

function recipeCard(recipe) {
    const imagePath = String(recipe.image || '');
    const image = escapeHtml(imagePath.startsWith('uploads/recipes/') ? `../${imagePath}` : '../assets/images/recipe-art.svg');
    return `<article class="admin-recipe-card">
        <img class="admin-recipe-image" src="${image}" alt="${escapeHtml(recipe.name)}" loading="lazy">
        <div class="admin-recipe-info">
            <span class="admin-category">${escapeHtml(recipe.category)}</span>
            <h3>${escapeHtml(recipe.name)}</h3>
            <p>${escapeHtml(recipe.description)}</p>
            <small>${escapeHtml(recipe.duration)} menit · ${escapeHtml(recipe.difficulty)} · ${escapeHtml(recipe.servings)} porsi</small>
        </div>
        <div class="admin-card-actions">
            <button type="button" class="secondary-button" data-edit-recipe="${escapeHtml(recipe.id)}" aria-label="Edit ${escapeHtml(recipe.name)}">✎ Edit</button>
            <button type="button" class="danger-button" data-delete-recipe="${escapeHtml(recipe.id)}" aria-label="Hapus ${escapeHtml(recipe.name)}">Hapus</button>
        </div>
    </article>`;
}

function renderRecipes() {
    const query = document.getElementById('admin-search').value.trim().toLocaleLowerCase('id');
    const filtered = recipes.filter(recipe => (
        `${recipe.name} ${recipe.category} ${recipe.description}`.toLocaleLowerCase('id').includes(query)
    ));
    document.getElementById('recipe-total').textContent = String(recipes.length);
    if (!filtered.length) {
        recipeList.innerHTML = `<div class="empty-state">${query ? 'Tidak ada resep yang cocok dengan pencarian.' : 'Belum ada resep di katalog.'}</div>`;
        return;
    }
    recipeList.innerHTML = filtered.map(recipeCard).join('');
}

async function loadRecipes() {
    recipeList.innerHTML = '<div class="loading-state">Memuat resep...</div>';
    try {
        const result = await requestJson(`${adminApi}?action=admin_list`);
        recipes = result.recipes || [];
        renderRecipes();
    } catch (error) {
        recipeList.innerHTML = `<div class="empty-state">${escapeHtml(error.message)}</div>`;
        document.getElementById('recipe-total').textContent = '—';
    }
}

document.getElementById('new-recipe').addEventListener('click', () => {
    resetForm();
    showEditor();
    recipeForm.elements.name.focus();
});

document.getElementById('close-editor').addEventListener('click', () => {
    editorPanel.hidden = true;
});
document.getElementById('cancel-edit').addEventListener('click', () => {
    editorPanel.hidden = true;
});

imageInput.addEventListener('change', () => {
    const image = imageInput.files[0];
    if (!image) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(image.type) || image.size > 5 * 1024 * 1024) {
        imageInput.value = '';
        showToast('Pilih gambar JPG, PNG, atau WEBP maksimal 5 MB.');
        return;
    }
    clearImagePreview();
    document.getElementById('image-picker-label').textContent = image.name;
    previewUrl = URL.createObjectURL(image);
    imagePreview.innerHTML = `<img src="${escapeHtml(previewUrl)}" alt="Pratinjau gambar yang dipilih"><small>Gambar baru · ${escapeHtml(image.name)}</small>`;
});

document.getElementById('admin-search').addEventListener('input', renderRecipes);

recipeList.addEventListener('click', event => {
    const editButton = event.target.closest('[data-edit-recipe]');
    const deleteButton = event.target.closest('[data-delete-recipe]');
    if (editButton) {
        const recipe = recipes.find(item => Number(item.id) === Number(editButton.dataset.editRecipe));
        if (!recipe) return;
        setFormFromRecipe(recipe);
        showEditor();
    }
    if (deleteButton) {
        selectedDeleteId = Number(deleteButton.dataset.deleteRecipe);
        const recipe = recipes.find(item => Number(item.id) === selectedDeleteId);
        deleteDialog.querySelector('h2').textContent = `Hapus ${recipe ? recipe.name : 'resep ini'}?`;
        deleteDialog.showModal();
    }
});

recipeForm.addEventListener('submit', async event => {
    event.preventDefault();
    const submitButton = document.getElementById('save-recipe');
    const errorOutput = document.getElementById('form-error');
    const formData = new FormData(recipeForm);
    const id = formData.get('id');
    formData.set('action', id ? 'admin_update' : 'admin_create');
    formData.set('featured', recipeForm.elements.featured.checked ? '1' : '0');
    submitButton.disabled = true;
    errorOutput.textContent = '';
    try {
        const result = await requestJson(adminApi, {
            method: 'POST',
            headers: { 'X-CSRF-Token': adminCsrfToken },
            body: formData
        });
        showToast(result.message || 'Resep berhasil disimpan.');
        editorPanel.hidden = true;
        resetForm();
        await loadRecipes();
    } catch (error) {
        errorOutput.textContent = error.message;
    } finally {
        submitButton.disabled = false;
    }
});

document.querySelectorAll('[data-close-delete]').forEach(button => button.addEventListener('click', () => deleteDialog.close()));
deleteDialog.addEventListener('click', event => {
    if (event.target === deleteDialog) deleteDialog.close();
});
document.getElementById('confirm-delete').addEventListener('click', async event => {
    if (!selectedDeleteId) return;
    const button = event.currentTarget;
    button.disabled = true;
    try {
        const body = new FormData();
        body.set('action', 'admin_delete');
        body.set('id', String(selectedDeleteId));
        const result = await requestJson(adminApi, {
            method: 'POST',
            headers: { 'X-CSRF-Token': adminCsrfToken },
            body
        });
        deleteDialog.close();
        selectedDeleteId = null;
        showToast(result.message || 'Resep berhasil dihapus.');
        await loadRecipes();
    } catch (error) {
        showToast(error.message);
    } finally {
        button.disabled = false;
    }
});

loadRecipes();
loadAdminAccounts();
const route = new URLSearchParams(window.location.search);
if (route.get('mode') === 'create') {
    resetForm();
    showEditor();
}
if (route.has('edit') || route.has('id')) {
    const recipeId = Number(route.get('edit') || route.get('id'));
    const findRecipe = setInterval(() => {
        const recipe = recipes.find(item => Number(item.id) === recipeId);
        if (recipe) {
            clearInterval(findRecipe);
            setFormFromRecipe(recipe);
            showEditor();
        }
    }, 100);
    setTimeout(() => clearInterval(findRecipe), 10000);
}
