let refreshTimer = null;
let refreshInterval = 30;
function getCurrentPower(device, noSuffix=false){
    w = '';
    if (!device.online) return 'Offline';
    device.status.forEach((status, index) => {
        if (status.code === 'cur_power'){
            w = device.status[0].value ? (status.value / 10) : 0;
        }
    });
    if (noSuffix) return w;
    if (isNumber(w)){
        if (w > 1000){
            w = Math.round((w/1000), 3) + ' kWh';
        }else{
            w += ' W';
        }
    }
    return w;
}
async function getDeviceList(){
    const element = document.getElementById('tuya_device_list');
    if (element) {
        try {
            const response = await fetch('/api/get/tuya_device_list', {
                method: 'GET'
            });
            if (!response.ok) throw new Error(`Unable to fetch device list`);
            const result = await response.json();
            html = '';
            result.devices.forEach((device, index) => {
                html += '<div class="device-card prevent-select' + (device.online ? '' : ' disabled') + '" id="' + device.id + '">' + 
                    '<div class="header"><a href="?tuya_device_id=' + device.id + '">' + device.name.trim() + '</a></div>' + 
                    '<div class="icon"><img src="' + device.icon + '"></div>'+ 
                    '<div class="status">' + getCurrentPower(device) + '</div>'+ 
                    '<div class="switch"><label class="toggle-container"><input type="checkbox" name="' + device.id + '_switch" id="' + device.id + '_switch" class="toggle-checkbox switch-checkbox" data-endpoint="/api/tuya/set_switch_status" data-id="' + device.id + '" ' + (device.status[0].value ? 'checked' : '') + '' + (device.online ? '' : ' disabled') + '><span class="toggle-track"></span></label></div>'+ 
                    '</div>';
            });
            element.innerHTML = '<div class="device-cards">' + html + '</div>';    
        } catch (error) {
            showNotification(`Error: ${error.message}`);
        }
    }
}

async function refreshCards(){
    // Skip if not enabled
    if (!document.getElementById('tuya_auto_refresh').checked) return;

    const element = document.getElementById('tuya_device_list');
    if (element) {
        try {
            await waitForFocus();
            const response = await fetch('/api/get/tuya_device_list', {
                method: 'GET'
            });
            if (!response.ok) throw new Error(`Unable to fetch device list`);
            const result = await response.json();
            html = '';
            result.devices.forEach((device, index) => {
                const card = document.getElementById(device.id);
                if (card){
                    card.classListt = 'device-card prevent-select' + (device.online ? '' : ' disabled');
                    card.querySelector('.header').innerText = device.name.trim() + (device.online ? '' : ' - Offline');
                    card.querySelector('.status').innerText = getCurrentPower(device);
                    const checkbox = card.querySelector('.switch-checkbox');
                    checkbox.disabled = !device.online; // Disable if offline
                    checkbox.checked = device.status[0].value; // Update checked status
                }else{
                    html += '<div class="device-card prevent-select' + (device.online ? '' : ' disabled') + '" id="' + device.id + '">' + 
                        '<div class="header"><a href="?tuya_device_id=' + device.id + '">' + device.name.trim() + (device.online ? '' : ' - Offline') + '</a></div>' + 
                        '<div class="icon"><img src="' + device.icon + '"></div>'+ 
                        '<div class="status">' + getCurrentPower(device) + '</div>'+ 
                        '<div class="switch"><label class="toggle-container"><input type="checkbox" name="' + device.id + '_switch" id="' + device.id + '_switch" class="toggle-checkbox switch-checkbox" data-endpoint="/api/tuya/set_switch_status" data-id="' + device.id + '" ' + (device.status[0].value ? 'checked' : '') + '' + (device.online ? '' : ' disabled') + '><span class="toggle-track"></span></label></div>'+ 
                        '</div>';
                    element.getElementsByClassName('device-cards').innerHTML += html;
                }
            });
        } catch (error) {
            showNotification(`Error: ${error.message}`);
        }
    }

    // Clear existing timer if it’s already running
    if (refreshTimer) {
        clearTimeout(refreshTimer);
    }

    // Schedule new timeout
    refreshTimer = setTimeout(refreshCards, refreshInterval * 1000);
}
async function refreshDevice(){
    // Skip if not enabled
    if (!document.getElementById('tuya_auto_refresh').checked) return;

    const element = document.getElementById('tuya-device-details');
    if (element) {
        try {
            await waitForFocus();
            const formData = new FormData();

            // Append fields
            formData.append('device', element.dataset.deviceId);
            const response = await fetch('/api/tuya/device', {
                method: 'POST',
                body: formData
            });
            if (!response.ok) throw new Error(`Unable to fetch device details`);
            const result = await response.json();
            html = '';
            
            if (result.device){
                device = result.device;
                const card = document.getElementById('tuya-device-details');
                card.querySelector('.device-image-can').classList = 'device-image-can ' + (device.online ? '' : ' disabled');
                card.querySelector('#name').innerText = device.name.trim();
                card.querySelector('#status').innerText = (device.online ? 'Online' : 'Offline');
                const checkbox = card.querySelector('.switch-checkbox');
                checkbox.disabled = !device.online; // Disable if offline
                checkbox.checked = device.status[0].value; // Update checked status

                let now = new Date();
                let hours = formatTime(now.getHours());
                let minutes = formatTime(now.getMinutes());
                let seconds = formatTime(now.getSeconds());
                if (document.getElementById('tuya-device-energy-consumption') && device.online && isNumber(getCurrentPower(device, true))) {
                    Plotly.extendTraces('tuya-device-energy-consumption', {
                        x: [[`${hours}:${minutes}:${seconds}`]],
                        y: [[getCurrentPower(device, true)]]
                    }, [0]);

                }
            };
        } catch (error) {
            showNotification(`Error: ${error.message}`);
        }
    }

    // Clear existing timer if it’s already running
    if (refreshTimer) {
        clearTimeout(refreshTimer);
    }

    // Schedule new timeout
    refreshTimer = setTimeout(refreshDevice, refreshInterval * 1000);
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
function renderChart(chartId) {
    const chartContainer = document.getElementById(chartId);
    if (chartContainer) {
        const layout = {
            xaxis: {
                title: 'Time',
                showgrid: true,
                showticklabels: true,
                color: '#fff',
                linecolor: '#100030',
                gridcolor: '#100030',
                rangemode: 'tozero',
                autorange: true,
            },
            yaxis: {
                title: {
                    text: 'Watts',
                    standoff: 15,  
                },
                showgrid: true,
                color: '#fff',
                linecolor: '#100030',
                gridcolor: '#100030',
                rangemode: 'tozero',
                autorange: true,
            },
            annotations: [{
                text: 'No Data Available',
                xref: 'paper',
                yref: 'paper',
                x: 0.5,
                y: 0.5,
                showarrow: false,
                font: {
                    size: 18,
                    color: '#fff'
                },
                align: 'center'
            }],
            autosize: true,
            margin: {
                t: 10,
                b: 40,
                l: 50,
                r: 10
            },
            paper_bgcolor: '#180048',
            plot_bgcolor: '#180048'
        };

        // Empty data array
        const data = [];
        const config = {
            displayModeBar: false,
            staticPlot: true
        };
        Plotly.newPlot(chartId, data, layout, config);
    
        // Start creating lines on graph
        var time = [];
        var watts = [];
        if (document.getElementById(chartId).dataset.initialWatts){
            let now = new Date();
            let hours = formatTime(now.getHours());
            let minutes = formatTime(now.getMinutes());
            let seconds = formatTime(now.getSeconds());
            time = [`${hours}:${minutes}:${seconds}`];
            watts = [document.getElementById(chartId).dataset.initialWatts];
 
            // remove the no data annotation
            Plotly.relayout(chartId, { annotations: [] });
       }  
        
        // Make the traces
        const trace = {
            x: time,
            y: watts,
            type: 'scatter',
            mode: 'lines+markers',
            name: 'Energy Usage',
            line: { color: '#5840ff', width: 3 }
        };

        // Render the chart with initial data
        Plotly.react(chartId, [trace], layout);
    }
}
document.addEventListener('DOMContentLoaded', async function() {
    getDeviceList();

    if (document.getElementById('tuya-device-list')){
        refreshInterval = document.getElementById('tuya_auto_refresh').dataset.interval ?? 30;
        refreshTimer = setTimeout(refreshCards, refreshInterval * 1000);
    }
    if (document.getElementById('tuya-device-details')){
        refreshInterval = document.getElementById('tuya_auto_refresh').dataset.interval ?? 30;
        refreshTimer = setTimeout(refreshDevice, refreshInterval * 1000);
    }
    if (document.getElementById('tuya-device-energy-consumption')){
        renderChart('tuya-device-energy-consumption');
    }

    // Listen on a parent element for changes to dynamically added checkboxes
    document.addEventListener('change', function(event) {
        // Check if the changed element is one of our dynamic checkboxes
        if (event.target.matches('.switch-checkbox')) {
            handleToggleChange(event.target);
        }
        if (event.target.matches('#tuya_auto_refresh')) {
            refreshCards();
        }
    });
});
