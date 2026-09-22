function renderPagination(totalPages) {
    const container = document.getElementById('paginationControls');
    let html = '';

    html += `<button class="btn-page" ${currentPage === 1 ? 'disabled' : ''} onclick="goToPage(1)">«</button>`;

    html += `<button class="btn-page" ${currentPage === 1 ? 'disabled' : ''} onclick="goToPage(${currentPage - 1})">‹</button>`;

    let start = Math.max(1, currentPage - 2);
    let end = Math.min(totalPages, start + 4);

    if (end - start < 4) {
        start = Math.max(1, end - 4);
    }

    for (let i = start; i <= end; i++) {
        html += `
            <button class="btn-page ${i === currentPage ? 'active' : ''}"
                    onclick="goToPage(${i})">
                ${i}
            </button>
        `;
    }

    html += `<button class="btn-page" ${currentPage === totalPages ? 'disabled' : ''} onclick="goToPage(${currentPage + 1})">›</button>`;

    html += `<button class="btn-page" ${currentPage === totalPages ? 'disabled' : ''} onclick="goToPage(${totalPages})">»</button>`;

    container.innerHTML = html;
}