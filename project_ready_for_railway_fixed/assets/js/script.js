document.addEventListener('DOMContentLoaded', function() {
    // Theme switcher and other previous code may be here
    // ...

    // Today's expiry check
    checkTodaysExpiry();

    // --- New: Event listener for barcode search ---
    const barcodeModal = document.getElementById('barcodeSearchModal');
    const barcodeInput = document.getElementById('barcodeInput');
    const barcodeResult = document.getElementById('barcodeResult');
    let searchTimeout;

    // When the modal is opened, it will focus on the input field
    barcodeModal.addEventListener('shown.bs.modal', () => {
        barcodeInput.focus();
        barcodeInput.value = ''; // Clear the input field
        barcodeResult.innerHTML = '<p class="text-center text-muted">Waiting for barcode scan...</p>';
    });

    // When something is typed in the input field (scanned)
    barcodeInput.addEventListener('input', function() {
        // Cancel previous search
        clearTimeout(searchTimeout);
        
        const barcode = this.value.trim();
        
        if (barcode.length > 3) { // Search will start after a certain length
            barcodeResult.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            
            // Will wait 500 milliseconds for typing to finish
            searchTimeout = setTimeout(() => {
                searchByBarcode(barcode);
            }, 500);
        }
    });
});

// Function to check today's expiry
function checkTodaysExpiry() {
    fetch('api_check_expiry.php')
        .then(response => response.json())
        .then(data => {
            if (data.expiredToday > 0) {
                // Sound or voice alert code will be here
            }
        })
        .catch(error => console.error('Error checking expiry:', error));
}

// --- New: Function to search by barcode ---
function searchByBarcode(barcode) {
    fetch('api_search_barcode.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ barcode: barcode })
    })
    .then(response => response.json())
    .then(data => {
        const barcodeResult = document.getElementById('barcodeResult');
        if (data.success) {
            const item = data.item;
            
            // Determine status
            const today = new Date();
            const expiryDate = new Date(item.expiry_date);
            today.setHours(0, 0, 0, 0);
            expiryDate.setHours(0, 0, 0, 0);
            const diffTime = expiryDate - today;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            let statusClass = 'text-success';
            let statusText = 'OK';
            if (diffDays < 0) {
                statusClass = 'text-danger';
                statusText = `Expired (${Math.abs(diffDays)} days ago)`;
            } else if (diffDays === 0) {
                statusClass = 'text-danger';
                statusText = 'Expiring Today';
            } else if (diffDays <= 7) {
                statusClass = 'text-warning';
                statusText = `${diffDays} days remaining`;
            }

            // Display result as a card
            barcodeResult.innerHTML = `
                <div class="card item-result-card">
                    <div class="row g-0">
                        <div class="col-4">
                            <img src="${item.image ? 'uploads/' + item.image : 'assets/images/placeholder.png'}" class="img-fluid rounded-start" alt="${item.name}">
                        </div>
                        <div class="col-8">
                            <div class="card-body">
                                <h5 class="card-title">${item.name}</h5>
                                <p class="card-text mb-1"><strong>Category:</strong> ${item.category}</p>
                                <p class="card-text mb-1"><strong>Quantity:</strong> ${item.quantity}</p>
                                <p class="card-text mb-2"><strong>Expiry:</strong> ${new Date(item.expiry_date).toLocaleDateString('en-US', { day: 'numeric', month: 'long', year: 'numeric' })}</p>
                                <p class="card-text fw-bold ${statusClass}">${statusText}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        } else {
            barcodeResult.innerHTML = `<div class="alert alert-danger text-center">${data.message}</div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('barcodeResult').innerHTML = `<div class="alert alert-danger text-center">Problem connecting to the server.</div>`;
    });
}
