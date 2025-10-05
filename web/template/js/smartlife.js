
function getCurrentPower(device){
    w = '';
    device.status.forEach((status, index) => {
        if (status.code === 'cur_power'){
            w = (status.value / 10);
        }
    });
    if (isNumber(w)){
        if (w > 1000){
            w = Math.round((w/1000), 3) + ' kWh';
        }else{
            w += ' W'
        }
    }
    return w;
}
async function getDeviceList(){
    const element = document.getElementById('tuya_device_list');
    if (element) {
        try {
            const response = await fetch('/get/tuya_device_list', {
                method: 'GET'
            });
            if (!response.ok) throw new Error(`Unable to fetch device list`);
            const result = await response.json();
            html = '';
            result.devices.forEach((device, index) => {
                html += '<div class="device-card" id="' + device.id + '">' + 
                    '<div class="header">' + device.name.trim() + (device.online ? '' : ' - Offline') + '</div>' + 
                    '<div class="icon"><img src="' + device.icon + '"></div>'+ 
                    '<div class="status">' + getCurrentPower(device) + '</div>'+ 
                    '<div class="switch"><label class="toggle-container"><input type="checkbox" name="' + device.id + '_switch" id="' + device.id + '_switch" class="toggle-checkbox" data-endpoint="/api/tuya/set_switch_status" data-id="' + device.id + '" ' + (device.status[0].value ? 'checked' : '') + '><span class="toggle-track"></span></label></div>'+ 
                    '</div>';
            });
            element.innerHTML = '<div class="device-cards">' + html + '</div>';    
        } catch (error) {
            showNotification(`Error: ${error.message}`);
        }
    }
}



async function handleToggleChange(checkbox) {
    checkbox.disabled = true; // Disable the checkbox to prevent multiple clicks
    const endpoint = checkbox.dataset.endpoint;
    const deviceId = checkbox.dataset.id;
    const isChecked = checkbox.checked;
    
    console.log(`Dynamic checkbox changed: ${deviceId} = ${isChecked}`);
    
    try {
        // Create form data to submit
        const formData = new FormData();

        // Append fields
        formData.append('device', deviceId);
        formData.append('value', isChecked);

        const response = await fetch(endpoint, {
            method: 'POST',
            body: formData
        });

        // Check if the response is OK (status 200-299)
        if (!response.ok) {
            showNotification(`Error! status: ${response.status} - ${response.statusText}`);
        }

        // Try to parse JSON
        const data = await response.json();

        showNotification(data.message || 'Success!');

    } catch (error) {
        console.error('Fetch error:', error);
        showNotification(`Unknown Error: ${error.message}`);
    }
    checkbox.disabled = false; // Re-enable the checkbox
}
document.addEventListener('DOMContentLoaded', async function() {

    getDeviceList();

    // Listen on a parent element for changes to dynamically added checkboxes
    document.addEventListener('change', function(event) {
        // Check if the changed element is one of our dynamic checkboxes
        if (event.target.matches('.toggle-checkbox')) {
            handleToggleChange(event.target);
        }
    });
});
