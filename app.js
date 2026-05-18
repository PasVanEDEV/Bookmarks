const btnTheme = document.getElementById("btnTheme");
const currentTheme = localStorage.getItem("theme") || "light";

if (btnTheme) {
  if (currentTheme === "dark") {
    btnTheme.textContent = "☀️";
  }
  btnTheme.onclick = () => {
    const isDark = document.documentElement.getAttribute("data-theme") === "dark";
    document.documentElement.setAttribute("data-theme", isDark ? "light" : "dark");
    localStorage.setItem("theme", isDark ? "light" : "dark");
    btnTheme.textContent = isDark ? "🌙" : "☀️";
  };
}

const state = { bookmarks: [], categoryOrder: [], query: "", selectedCategories: [], editingId: null };

const categoryColors = {
  "Muziek": { a: "#1DB954", s: "rgba(29, 185, 84, 0.14)" },
  "Social": { a: "#1877F2", s: "rgba(24, 119, 242, 0.14)" },
  "TV&Film": { a: "#E50914", s: "rgba(229, 9, 20, 0.14)" },
  "AI": { a: "#10a37f", s: "rgba(16, 163, 127, 0.14)" },
  "Shopping": { a: "#0000a4", s: "rgba(0, 0, 164, 0.14)" },
  "Vakantie": { a: "#003580", s: "rgba(0, 53, 128, 0.14)" },
  "ICT": { a: "#b90000", s: "rgba(185, 0, 0, 0.14)" },
  "Weer": { a: "#0ea5e9", s: "rgba(14, 165, 233, 0.14)" },
  "Cyber": { a: "#00B2A9", s: "rgba(0, 178, 169, 0.14)" },
  "Werk": { a: "#0058b8", s: "rgba(0, 88, 184, 0.14)" },
  "Financieel": { a: "#059669", s: "rgba(5, 150, 105, 0.14)" },
  "Nieuws": { a: "#1e293b", s: "rgba(30, 41, 59, 0.14)" },
  "Vrijetijd": { a: "#ea580c", s: "rgba(234, 88, 12, 0.14)" }
};

const els = {
  grid: document.getElementById("grid"),
  empty: document.getElementById("emptyState"),
  search: document.getElementById("searchInput"),
  pills: document.getElementById("categoryPills"),
  resultsCount: document.getElementById("resultsCount"),
  btnAdd: document.getElementById("btnAdd"),
  btnClear: document.getElementById("btnClearFilters"),
  categorySelect: document.getElementById("categorySelect"),
  toast: document.getElementById("toast"),
  toastTitle: document.getElementById("toastTitle"),
  toastMessage: document.getElementById("toastMessage"),
  toastClose: document.getElementById("toastClose"),
  dialog: document.getElementById("editorDialog"),
  dialogTitle: document.getElementById("dialogTitle"),
  btnDialogClose: document.getElementById("btnDialogClose"),
  btnCancel: document.getElementById("btnCancel"),
  btnSave: document.getElementById("btnSave"),
  btnDeleteInDialog: document.getElementById("btnDeleteInDialog"),
  form: document.getElementById("bookmarkForm"),
  fieldTitle: document.getElementById("fieldTitle"),
  fieldUrl: document.getElementById("fieldUrl"),
  fieldCategory: document.getElementById("fieldCategory"),
  fieldNotes: document.getElementById("fieldNotes"),
  formError: document.getElementById("formError")
};

document.addEventListener('keydown', (e) => {
  if (els.search && els.dialog) {
    if (e.key === '/' && document.activeElement !== els.search && !els.dialog.open) {
      e.preventDefault();
      els.search.focus();
    }
    if (e.key === 'Escape' && document.activeElement === els.search) {
      els.search.value = "";
      state.query = "";
      render();
      els.search.blur();
    }
  }
});

if (els.categorySelect) {
  els.categorySelect.addEventListener('change', (e) => {
    if(e.target.value) {
      els.fieldCategory.value = e.target.value;
      e.target.value = ""; 
    }
  });
}

function uid() { 
    return crypto.randomUUID ? crypto.randomUUID() : "bm_" + Math.random().toString(16).slice(2) + "_" + Date.now().toString(16); 
}
function normalizeCategory(c){ return (c || "").trim().replace(/\s+/g, " "); }
function safeText(v){ return (v ?? "").toString().trim(); }
function isValidHttpUrl(v){ try{ const u = new URL(v); return u.protocol === "http:" || u.protocol === "https:"; }catch{ return false; } }
function getHostname(v){ try{ return new URL(v).hostname.replace(/^www\./, ""); }catch{ return ""; } }

// Voorkomt Cross-Site Scripting (XSS) bij dynamische data output
function escapeHTML(str) {
  return str.replace(/[&<>'"]/g, tag => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
  }[tag]));
}

async function loadData(){
  try {
    const response = await fetch('load.php?t=' + Date.now());
    if(response.status === 403) { window.location.reload(); return { bookmarks: [], categoryOrder: [] }; }
    if(!response.ok) throw new Error("Netwerk error");
    const data = await response.json();
    return {
      bookmarks: Array.isArray(data.bookmarks) ? data.bookmarks : [],
      categoryOrder: Array.isArray(data.categoryOrder) ? data.categoryOrder : []
    };
  } catch (e) {
    console.error("Fout bij laden.", e);
    return { bookmarks: [], categoryOrder: [] };
  }
}

function saveData(){
  const payload = { 
    version: 1, 
    exportedAt: new Date().toISOString(), 
    bookmarks: state.bookmarks,
    categoryOrder: state.categoryOrder
  };
  
  fetch('save.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => { 
    if(r.status === 403) window.location.reload();
    if(!r.ok) console.error("Server save failed"); 
  })
  .catch(e => console.error("Network error:", e));
}

let toastTimer = null;
function showToast(t, m){
  els.toastTitle.textContent = t;
  els.toastMessage.textContent = m;
  els.toast.dataset.open = "true";
  if(toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => els.toast.dataset.open = "false", 2600);
}

function getOrderedCategories() {
  const currentCats = [...new Set(state.bookmarks.map(b => normalizeCategory(b.category)))];
  const newCats = currentCats.filter(c => !state.categoryOrder.includes(c)).sort();
  state.categoryOrder = [...state.categoryOrder.filter(c => currentCats.includes(c)), ...newCats];
  return state.categoryOrder;
}

function render(){
  if (!els.grid) return;
  const q = state.query.toLowerCase();
  const filtered = state.bookmarks.filter(b => {
    const cat = normalizeCategory(b.category);
    if(state.selectedCategories.length > 0 && !state.selectedCategories.includes(cat)) return false;
    return !q || (b.title + " " + b.category).toLowerCase().includes(q);
  });
  renderPills();
  renderGrid(filtered);
  els.resultsCount.textContent = `${filtered.length} weergegeven van ${state.bookmarks.length}`;
}

let draggedCat = null;
let draggedBmId = null;
let draggedCatCard = null;

function renderPills(){
  const cats = getOrderedCategories();
  
  els.pills.innerHTML = "";
  els.categorySelect.innerHTML = `<option value="" disabled selected>Kies...</option>`; 
  
  const btnAll = document.createElement("button");
  btnAll.className = "pill";
  btnAll.textContent = "Alle"; 
  btnAll.setAttribute("aria-pressed", state.selectedCategories.length === 0);
  btnAll.onclick = () => { state.selectedCategories = []; render(); };
  els.pills.appendChild(btnAll);

  cats.forEach(c => {
    const btn = document.createElement("button");
    btn.className = "pill";
    btn.textContent = c;
    const isSelected = state.selectedCategories.includes(c);
    btn.setAttribute("aria-pressed", isSelected);
    btn.draggable = true;
    
    btn.onclick = () => { 
      if (isSelected) {
        state.selectedCategories = state.selectedCategories.filter(cat => cat !== c);
      } else {
        state.selectedCategories.push(c);
      }
      render(); 
    };
    
    btn.ondragstart = (e) => {
      draggedCat = c;
      e.dataTransfer.effectAllowed = 'move';
      setTimeout(() => btn.classList.add('dragging'), 0);
    };
    btn.ondragend = () => {
      draggedCat = null;
      btn.classList.remove('dragging');
      document.querySelectorAll('.pill').forEach(p => p.classList.remove('drag-over'));
    };
    btn.ondragover = (e) => {
      e.preventDefault(); 
      if(draggedCat && draggedCat !== c) btn.classList.add('drag-over');
    };
    btn.ondragleave = () => {
      btn.classList.remove('drag-over');
    };
    btn.ondrop = (e) => {
      e.preventDefault();
      btn.classList.remove('drag-over');
      if (draggedCat && draggedCat !== c) {
        const fromIdx = state.categoryOrder.indexOf(draggedCat);
        const toIdx = state.categoryOrder.indexOf(c);
        state.categoryOrder.splice(fromIdx, 1);
        state.categoryOrder.splice(toIdx, 0, draggedCat);
        saveData();
        render();
      }
    };

    els.pills.appendChild(btn);

    const opt = document.createElement("option");
    opt.value = c;
    opt.textContent = c;
    els.categorySelect.appendChild(opt);
  });
}

function renderGrid(items){
  els.grid.innerHTML = "";
  const activeCats = getOrderedCategories().filter(cat => items.some(b => normalizeCategory(b.category) === cat));
  
  const fragment = document.createDocumentFragment();
  
  activeCats.forEach(cat => {
    const panel = document.createElement("section");
    panel.className = "categoryCard";
    panel.draggable = true;
    
    if (cat === "Google") {
      panel.classList.add("google-card");
      panel.style.setProperty('--cat-accent', '#4285F4');
      panel.style.setProperty('--cat-accent-soft', 'rgba(66, 133, 244, 0.14)');
    } else if (categoryColors[cat]) {
      panel.style.setProperty('--cat-accent', categoryColors[cat].a);
      panel.style.setProperty('--cat-accent-soft', categoryColors[cat].s);
    }
    
    panel.innerHTML = `
      <div class="categoryHeader">
        <h2 class="categoryTitle">${escapeHTML(cat)}</h2>
      </div>
      <ul class="bookmarkList"></ul>`;
      
    const list = panel.querySelector("ul");
    
    panel.ondragstart = (e) => {
      if(draggedBmId) { e.preventDefault(); return; }
      draggedCatCard = cat;
      e.dataTransfer.effectAllowed = 'move';
      setTimeout(() => panel.classList.add('dragging'), 0);
    };
    
    panel.ondragend = () => {
      draggedCatCard = null;
      panel.classList.remove('dragging');
      document.querySelectorAll('.categoryCard').forEach(p => p.classList.remove('drag-over'));
    };
    
    panel.ondragover = (e) => {
      if(draggedCatCard && draggedCatCard !== cat) {
        e.preventDefault();
        panel.classList.add('drag-over');
      }
    };
    
    panel.ondragleave = () => {
      panel.classList.remove('drag-over');
    };
    
    panel.ondrop = (e) => {
      if (draggedCatCard && draggedCatCard !== cat) {
        e.preventDefault();
        e.stopPropagation();
        panel.classList.remove('drag-over');
        
        const fromIdx = state.categoryOrder.indexOf(draggedCatCard);
        const toIdx = state.categoryOrder.indexOf(cat);
        if (fromIdx > -1 && toIdx > -1) {
          state.categoryOrder.splice(fromIdx, 1);
          state.categoryOrder.splice(toIdx, 0, draggedCatCard);
          saveData();
          render();
        }
      }
    };
    
    items.filter(b => normalizeCategory(b.category) === cat).forEach(b => {
      const li = document.createElement("li");
      li.className = "bookmarkRow";
      li.draggable = true;
      
      const faviconUrl = escapeHTML(`https://www.google.com/s2/favicons?domain=${getHostname(b.url)}&sz=32`);
      const safeTitle = escapeHTML(b.title);
      const safeUrl = escapeHTML(b.url);
      const safeHost = escapeHTML(getHostname(b.url));
      
      li.innerHTML = `
        <div class="bookmarkMain">
          <div style="display:flex; align-items:center; gap:8px;">
            <img src="${faviconUrl}" alt="" style="width:18px; height:18px; border-radius:4px; flex-shrink:0;" loading="lazy">
            <a class="bookmarkLink" href="${safeUrl}" target="_blank" rel="noopener noreferrer">${safeTitle}</a>
          </div>
          <div class="bookmarkSub">${safeHost}</div>
        </div>
        <div class="rowActions">
          <button class="rowBtn" onclick="openEdit('${escapeHTML(b.id)}')" title="Edit">✎</button>
          <button class="rowBtn rowBtnDanger" onclick="confirmDelete('${escapeHTML(b.id)}', '${safeTitle.replace(/'/g, "\\'")}')" title="Delete">🗑</button>
        </div>`;
        
      li.ondragstart = (e) => {
        e.stopPropagation(); 
        draggedBmId = b.id;
        e.dataTransfer.effectAllowed = 'move';
        setTimeout(() => li.classList.add('dragging'), 0);
      };
      li.ondragend = (e) => {
        e.stopPropagation();
        draggedBmId = null;
        li.classList.remove('dragging');
        document.querySelectorAll('.bookmarkRow').forEach(p => p.classList.remove('drag-over'));
      };
      li.ondragover = (e) => {
        if (draggedBmId && draggedBmId !== b.id) {
          e.preventDefault();
          e.stopPropagation();
          li.classList.add('drag-over');
        }
      };
      li.ondragleave = (e) => {
        e.stopPropagation();
        li.classList.remove('drag-over');
      };
      li.ondrop = (e) => {
        if (draggedBmId && draggedBmId !== b.id) {
          e.preventDefault();
          e.stopPropagation();
          li.classList.remove('drag-over');
          
          const fromIdx = state.bookmarks.findIndex(x => x.id === draggedBmId);
          const toIdx = state.bookmarks.findIndex(x => x.id === b.id);
          
          if (fromIdx > -1 && toIdx > -1) {
            const [movedItem] = state.bookmarks.splice(fromIdx, 1);
            movedItem.category = b.category;
            state.bookmarks.splice(toIdx, 0, movedItem);
            
            saveData();
            render();
          }
        }
      };

      list.appendChild(li);
    });
    fragment.appendChild(panel);
  });
  
  els.grid.appendChild(fragment);
  els.empty.hidden = items.length > 0;
}

window.openEdit = (id) => {
  const b = state.bookmarks.find(x => x.id === id);
  if(b) openDialog("edit", b);
};

window.confirmDelete = (id, t) => {
  if(confirm(`Delete "${t}"?`)){
    state.bookmarks = state.bookmarks.filter(x => x.id !== id);
    saveData();
    render();
    showToast("Deleted", "Bookmark deleted.");
  }
};

function openDialog(mode, b){
  state.editingId = mode === "edit" ? b.id : null;
  els.dialogTitle.textContent = mode === "edit" ? "Edit bookmark" : "Add a bookmark";
  els.btnDeleteInDialog.hidden = mode !== "edit";
  els.fieldTitle.value = b?.title || "";
  els.fieldUrl.value = b?.url || "";
  els.fieldCategory.value = b?.category || "";
  els.fieldNotes.value = b?.notes || "";
  els.dialog.showModal();
}

if (els.form) {
  els.form.onsubmit = (e) => {
    e.preventDefault();
    const b = {
      title: safeText(els.fieldTitle.value),
      url: safeText(els.fieldUrl.value),
      category: normalizeCategory(els.fieldCategory.value),
      notes: safeText(els.fieldNotes.value)
    };
    if(state.editingId){
      const idx = state.bookmarks.findIndex(x => x.id === state.editingId);
      state.bookmarks[idx] = { ...state.bookmarks[idx], ...b, updatedAt: Date.now() };
    } else {
      state.bookmarks.unshift({ id: uid(), ...b, createdAt: Date.now(), updatedAt: Date.now() });
    }
    saveData();
    els.dialog.close();
    render();
    showToast("Saved", "Bookmark saved.");
  };

  els.btnAdd.onclick = () => openDialog("add");
  els.btnCancel.onclick = () => els.dialog.close();
  els.btnDialogClose.onclick = () => els.dialog.close();
  
  els.btnClear.onclick = () => { state.query = ""; state.selectedCategories = []; els.search.value = ""; render(); };
  
  let searchTimeout;
  els.search.oninput = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      state.query = els.search.value;
      render();
    }, 200); 
  };
}

async function init(){
  if (!els.grid) return;
  const data = await loadData();
  if(!data) return; 
  
  state.bookmarks = data.bookmarks.map(b => ({
    ...b,
    title: safeText(b.title),
    url: safeText(b.url),
    category: normalizeCategory(b.category)
  })).filter(b => b.title && b.url && isValidHttpUrl(b.url));
  
  state.categoryOrder = data.categoryOrder;

  render();
}

init();