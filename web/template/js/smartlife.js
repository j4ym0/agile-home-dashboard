
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
                    '<div class="switch"><input type="checkbox" name="' + device.id + '_switch" id="' + device.id + '_switch" class="toggle-checkbox" data-endpoint="/save/save_setting" data-setting="' + device.name + '" ' + (device.status[0].value ? 'checked' : '') + '><span class="toggle-track"></span></div>'+ 
                    '</div>';
            });
            element.innerHTML = '<div class="device-cards">' + html + '</div>';    
        } catch (error) {
            showNotification(`Error: ${error.message}`);
        }
    }
}



document.addEventListener('DOMContentLoaded', async function() {

    getDeviceList();
});
