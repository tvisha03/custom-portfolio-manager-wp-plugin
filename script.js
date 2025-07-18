document.addEventListener('DOMContentLoaded', () => {
    // --- Dark Mode Toggle Logic (Existing) ---
    const toggleButton = document.getElementById('pm-dark-mode-toggle');
    const body = document.body;
    const darkModeClass = 'pm-dark-mode';
    const localStorageKey = 'pm-dark-mode-preference';
    const sunIcon = document.querySelector('.pm-icon-light');
    const moonIcon = document.querySelector('.pm-icon-dark');

    const updateIcon = () => {
        if (body.classList.contains(darkModeClass)) {
            if (sunIcon) sunIcon.style.display = 'none';
            if (moonIcon) moonIcon.style.display = 'inline-block';
        } else {
            if (sunIcon) sunIcon.style.display = 'inline-block';
            if (moonIcon) moonIcon.style.display = 'none';
        }
    };

    const savedPreference = localStorage.getItem(localStorageKey);
    if (savedPreference === 'enabled') {
        body.classList.add(darkModeClass);
    }
    updateIcon();

    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            body.classList.toggle(darkModeClass);
            if (body.classList.contains(darkModeClass)) {
                localStorage.setItem(localStorageKey, 'enabled');
            } else {
                localStorage.setItem(localStorageKey, 'disabled');
            }
            updateIcon();
        });
    }

    // --- Project Details Modal Logic (Existing) ---
    const projectItemsContainer = document.querySelector('.pm-portfolio-grid'); // This is the container for project items
    const modal = document.getElementById('pm-project-modal');
    const closeBtn = document.querySelector('.pm-modal-close');
    const modalTitle = document.getElementById('pm-modal-title');
    const modalImage = document.getElementById('pm-modal-image');
    const modalDescription = document.getElementById('pm-modal-description');
    const modalTechnologies = document.getElementById('pm-modal-technologies');
    const modalLiveLink = document.getElementById('pm-modal-live-link');
    const modalGithubLink = document.getElementById('pm-modal-github-link');

    const openModal = (projectData) => {
        modalTitle.textContent = projectData.title;
        modalDescription.textContent = projectData.description;
        modalTechnologies.innerHTML = projectData.technologies_html; // Populate with HTML chips

        if (projectData.image_url) {
            modalImage.src = projectData.image_url;
            modalImage.alt = projectData.title;
            modalImage.style.display = 'block';
        } else {
            modalImage.style.display = 'none';
        }

        if (projectData.live_url) {
            modalLiveLink.href = projectData.live_url;
            modalLiveLink.style.display = 'inline-block';
        } else {
            modalLiveLink.style.display = 'none';
        }

        if (projectData.github_url) {
            modalGithubLink.href = projectData.github_url;
            modalGithubLink.style.display = 'inline-block';
        } else {
            modalGithubLink.style.display = 'none';
        }

        modal.style.display = 'block';
        body.style.overflow = 'hidden';
    };

    const closeModal = () => {
        modal.style.display = 'none';
        body.style.overflow = '';
    };

    // Close modal when 'X' is clicked
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    // Close modal when clicking outside content (on the overlay)
    window.addEventListener('click', (event) => {
        if (event.target == modal) {
            closeModal();
        }
    });

    // --- NEW: Sorting & Rendering Logic ---
    const sortBySelect = document.getElementById('pm-sort-by');
    const allProjectsData = window.pmProjectsData || []; // Global project data from PHP

    // Function to render projects based on sorted data
    const renderProjects = (projectsToRender) => {
        if (!projectItemsContainer) return; // Exit if container not found

        projectItemsContainer.innerHTML = ''; // Clear current projects

        if (projectsToRender.length === 0) {
            projectItemsContainer.innerHTML = '<p>No projects to display based on current filters/sort.</p>';
            return;
        }

        projectsToRender.forEach(project => {
            const projectItem = document.createElement('div');
            projectItem.classList.add('pm-portfolio-item');
            projectItem.dataset.projectId = project.id;

            let imageHtml = project.image_url ?
                `<div class="pm-portfolio-image">
                    <img src="${project.image_url}" alt="${project.title}">
                </div>` : '';

            let technologiesHtml = project.technologies ?
                `<div class="pm-portfolio-tech-chips">
                    <strong>Technologies:</strong>
                    ${project.technologies_html}
                </div>` : '';

            let liveLinkHtml = project.live_url ?
                `<a href="${project.live_url}" target="_blank" class="button pm-button pm-button-live">View Live</a>` : '';

            let githubLinkHtml = project.github_url ?
                `<a href="${project.github_url}" target="_blank" class="button pm-button pm-button-github">GitHub</a>` : '';

            projectItem.innerHTML = `
                ${imageHtml}
                <div class="pm-portfolio-content">
                    <h3>${project.title}</h3>
                    <p class="pm-portfolio-description">${project.description}</p>
                    ${technologiesHtml}
                    <div class="pm-project-links">
                        ${liveLinkHtml}
                        ${githubLinkHtml}
                    </div>
                </div>
            `;
            projectItemsContainer.appendChild(projectItem);
        });

        // Re-attach modal click listeners after re-rendering
        attachModalListeners();
    };

    // Function to attach click listeners for modal
    const attachModalListeners = () => {
        const currentProjectItems = document.querySelectorAll('.pm-portfolio-item');
        currentProjectItems.forEach(item => {
            item.removeEventListener('click', handleProjectItemClick); // Remove old listeners first
            item.addEventListener('click', handleProjectItemClick);
        });
    };

    // Handler for project item click (for modal)
    const handleProjectItemClick = (event) => {
        // Prevent opening modal if a link/button inside was clicked
        if (event.target.closest('.pm-button') || event.target.tagName === 'A') {
            return;
        }
        const projectId = event.currentTarget.dataset.projectId;
        const clickedProject = allProjectsData.find(p => p.id == projectId);

        if (clickedProject) {
            openModal(clickedProject);
        }
    };


    // Function to perform sorting
    const sortProjects = (projectsArray, sortBy) => {
        return [...projectsArray].sort((a, b) => { // Use spread to create a new array
            if (sortBy === 'latest') {
                return b.id - a.id; // Descending ID
            } else if (sortBy === 'oldest') {
                return a.id - b.id; // Ascending ID
            } else if (sortBy === 'title_asc') {
                return a.title.localeCompare(b.title); // Alphabetical A-Z
            } else if (sortBy === 'title_desc') {
                return b.title.localeCompare(a.title); // Alphabetical Z-A
            }
            return 0;
        });
    };

    // Initial render on page load
    renderProjects(sortProjects(allProjectsData, sortBySelect ? sortBySelect.value : 'latest'));

    // Add event listener for sorting dropdown
    if (sortBySelect) {
        sortBySelect.addEventListener('change', () => {
            const selectedSort = sortBySelect.value;
            const sorted = sortProjects(allProjectsData, selectedSort);
            renderProjects(sorted);
        });
    }
});