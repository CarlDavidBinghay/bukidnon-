// Function to close the modal
function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

// Example to open the modal (can be triggered as needed)
function openModal(title, description) {
    document.getElementById('modal-title').innerText = title;
    document.getElementById('modal-description').innerText = description;
    document.getElementById('modal').style.display = 'flex';
}

// Function to load a section dynamically based on link clicks
document.querySelectorAll('nav ul li a').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        loadSection(e.target.getAttribute('href'));
    });
});

function loadSection(url) {
    fetch(url)
        .then(response => response.text())
        .then(html => {
            document.getElementById('section-content').innerHTML = html;
        })
        .catch(error => console.error('Error loading section:', error));
}
