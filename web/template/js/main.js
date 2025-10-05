
function getMaxOfArray(numArray) {return numArray.reduce((max, value) => Math.max(max, value), Number.NEGATIVE_INFINITY);}
function getMinOrZeroOfArray(numArray) {return numArray.reduce((min, value) => Math.min(min, value), 0);}
function roundUpToNearestTen(number) {return Math.ceil(number / 10) * 10;}
function roundDownToNearestTen(number) {return Math.floor(number / 10) * 10;}
function isDictArray(arr) {if (!Array.isArray(arr)) {return false;}return arr.every(item => typeof item === 'object' && item !== null && !Array.isArray(item));}
function isNumber(value) {return typeof value === 'number' && !isNaN(value);}
function addSpin(e){
    e.dataset.text = e.innerText; // Store original text
    e.innerHTML = '<div class="spin"></div>';
    e.disabled = true;
}
function removeSpin(e){
    e.innerText = e.dataset.text; // Restore original text
    e.disabled = false;
}
function showNotification(m) {
    const c = document.getElementById('notification-container'); 
    const n = document.createElement('div');
    n.className = 'notification';
    n.textContent = m;
    c.appendChild(n);
    setTimeout(() => {
        n.classList.add('hide');
        setTimeout(() => {
            c.removeChild(n);
        }, 500);
    }, 12000);
}
function createActiveTimerEvent(callback, interval = 10000) {
    let lastExecution = 0;
    let rafId = null;
    let isActive = true;
    
    const checkAndExecute = (timestamp) => {
        if (!isActive) {
            rafId = null;
            return;
        }
        
        if (timestamp - lastExecution >= interval) {
            lastExecution = timestamp;
            try {
                callback();
            } catch (error) {
                console.error('Callback error:', error);
            }
        }
        
        rafId = requestAnimationFrame(checkAndExecute);
    };
    
    const start = () => {
        if (!rafId && isActive) {
            lastExecution = performance.now();
            rafId = requestAnimationFrame(checkAndExecute);
        }
    };
    
    const stop = () => {
        if (rafId) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
    };
    
    const handleVisibilityChange = () => {
        isActive = document.visibilityState === 'visible';
        if (isActive) {
            start();
        } else {
            stop();
        }
    };
    
    // Set up listeners
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('focus', () => { isActive = true; start(); });
    window.addEventListener('blur', () => { isActive = false; stop(); });
    
    // Start initially
    start();
    
    return { stop: () => { stop(); isActive = false; } };
}
async function updateElements(endpoint){
    try {
        const response = await fetch(endpoint, {
            method: 'GET'
        });
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        Object.entries(result).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.value = value;
            }
        });        
    } catch (error) {
        showNotification(`Error: ${error.message}`);
        console.error('Update error:', error);
    }
}

document.addEventListener('submit', async (e) => {
    const aE = document.activeElement;
    if (!e.target.dataset.endpoint) return;
    e.preventDefault(); // Prevent default form submission
  
    const form = e.target;
    form.querySelectorAll('[type="submit"]').forEach(e => {
        addSpin(e);
    });
    const formData = new FormData(e.target);
    formData.append('submit_type', aE.value); // Append submit type if exists
    const endpoint = `${form.dataset.endpoint}/${form.id}`; // Get endpoint from data attribute
  
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            body: formData
            // For JSON: 
            // headers: { 'Content-Type': 'application/json' },
            // body: JSON.stringify(Object.fromEntries(formData))
        });

        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    
        const result = await response.json();
        showNotification(result.message || 'Success!');

        if (form.hasAttribute('data-update')){
            updateElements(form.dataset.update)
        }
    
    } catch (error) {
        showNotification(`Error: ${error.message}`);
        console.error('Submission error:', error);
    }
    form.querySelectorAll('[type="submit"]').forEach(e => {
        removeSpin(e);
    });
});
document.querySelectorAll('.update-btn').forEach(button => {
    button.addEventListener('click', async (e) => {
        if (!e.target.dataset.endpoint) return;
        e.preventDefault(); // Prevent default form submission
        addSpin(e.target);

        const endpoint = `${e.target.dataset.endpoint}/${e.target.id}`; // Get endpoint from data attribute
        await updateElements(endpoint);
        showNotification(`Updated`);
        removeSpin(e.target);
    });
});
document.querySelectorAll('.settings-toggle').forEach(button => {
    button.addEventListener('change', async (e) => {
        if (!e.target.dataset.endpoint) return;
        e.preventDefault(); // Prevent default form submission
        e.target.disabled = true; // Disable to prevent multiple clicks
        const endpoint = `${e.target.dataset.endpoint}`; // Get endpoint from data attribute
        try {
            // Create form data to submit
            const formData = new FormData();

            // Append fields
            formData.append('name', e.target.dataset.setting);
            formData.append('value', e.target.checked);

            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        
            const result = await response.json();
            showNotification(result.message || 'Success!');
        
        } catch (error) {
            showNotification(`Error: ${error.message}`);
            console.error('Submission error:', error);
        }
        e.target.disabled = false; // Re-enable the checkbox
    });
});