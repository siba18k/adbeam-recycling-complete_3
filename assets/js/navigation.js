// Add this to the beginning of each protected page's JS file
document.addEventListener('DOMContentLoaded', () => {
    checkAuthStatus().then(isAuthenticated => {
        if (!isAuthenticated) {
            window.location.href = 'index.html';
        }
    });
});

async function checkAuthStatus() {
    try {
        const response = await fetch('/api/auth/me.php', {
            credentials: 'include'
        });
        
        if (response.ok) {
            const data = await response.json();
            return data.authenticated;
        }
        return false;
    } catch (error) {
        console.error('Auth check failed:', error);
        return false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Check auth status on every page load
    checkAuthStatus();
    
    // Add click handler for logout if the button exists
    document.getElementById('logoutBtn')?.addEventListener('click', handleLogout);
});

async function checkAuthStatus() {
    try {
        const response = await fetch('/api/auth/me.php', {
            credentials: 'include'
        });
        
        if (response.ok) {
            const data = await response.json();
            updateNavigation(data.authenticated);
        }
    } catch (error) {
        console.error('Auth check failed:', error);
    }
}

function updateNavigation(isLoggedIn) {
    const authRequiredLinks = document.querySelectorAll('.auth-required');
    const authLinks = document.querySelector('.auth-buttons');
    
    if (isLoggedIn) {
        // Show all auth-required links
        authRequiredLinks.forEach(link => {
            link.style.display = 'block';
        });
        
        // Update auth buttons
        if (authLinks) {
            authLinks.innerHTML = `
                <a href="/profile.html" class="btn btn-outline-primary me-2">My Profile</a>
                <button id="logoutBtn" class="btn btn-danger">Logout</button>
            `;
        }
    } else {
        // Hide auth-required links
        authRequiredLinks.forEach(link => {
            link.style.display = 'none';
        });
        
        // Update auth buttons
        if (authLinks) {
            authLinks.innerHTML = `
                <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#signinModal">Sign in</button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#signupModal">Sign up</button>
            `;
        }
        
        // If on a protected page, redirect to home
        if (document.querySelector('body.auth-required')) {
            window.location.href = 'index.html';
        }
    }
}

async function handleLogout() {
    try {
        const response = await fetch('/api/auth/logout.php', {
            method: 'POST',
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateNavigation(false);
            if (window.location.pathname !== '/index.html') {
                window.location.href = 'index.html';
            }
        }
    } catch (error) {
        console.error('Logout failed:', error);
    }
}
