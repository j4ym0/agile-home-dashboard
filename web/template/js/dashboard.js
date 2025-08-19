async function getPriceList(data) {
    // Get the container where we'll put our list
    const listContainer = document.getElementById('price_list');

    if (!listContainer) {
        return;
    }

    html = '';

    // Process each item in the response
    data.tariff.forEach(item => {
        // Format the date if needed (assuming valid_from is a timestamp)
        const utcDate = new Date(item.valid_from);
        const validFrom = String(utcDate.getHours()).padStart(2, '0') + ':' + String(utcDate.getMinutes()).padStart(2, '0');

        var colour = ' blank';
        if (item.price_inc_vat > 23.00){
            colour = ' red';
        }else if (item.price_inc_vat > data.average_price_inc_vat){
            colour =' yellow';
        }else if (item.price_inc_vat > 0){
            colour = ' green';
        }else if (item.price_inc_vat == 0){
            colour = ' blue';
        }else if (item.price_inc_vat === null) {
            colour = ' blank';
        }else{
            colour =' purple';
        }

        if (validFrom == '00:00'){html += `<div class="price_header">Night</div>`}
        if (validFrom == '06:00'){html += `<div class="price_header">Morning</div>`}
        if (validFrom == '12:00'){html += `<div class="price_header">Afternoon</div>`}
        if (validFrom == '18:00'){html += `<div class="price_header">Evening</div>`}
        // Create the content
        html += `<div class="price_card${colour}" id="${validFrom.replace(':','_')}"><div id="time">${validFrom}</div><div id="price">${(Number(item.price_inc_vat)).toFixed(2)}p/kWh</div></div>`;
    });

    listContainer.innerHTML = html;
    
}
async function makeDashboardGraph(data) {
    var x = [], y = [], a = [], c = [], g = [], electric_cost = [], gas_cost = [], standard_tariff = [];

    const graphContainer = document.getElementById('dashboard_graph');

    if (!graphContainer) {
        return;
    }

    // Time options
    const options = {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    };

    data.tariff.forEach(item => {
        x.push(new Date(item['valid_from']).toLocaleTimeString([], options));
        y.push(item['price_inc_vat']);
        a.push(data.average_price_inc_vat);
        standard_tariff.push(data.electricity_standard_tariff[0].value_inc_vat);
    });
    data.electricity.forEach((item, i) => {
        c.push(item['consumption']);
        electric_cost.push((data.tariff[i].price_inc_vat / 100) * item['consumption']);
    });

    x.push(' '+(new Date(data.tariff[data.tariff.length-1]['valid_to'])).toLocaleTimeString([], options));
    y.push(data.tariff[data.tariff.length-1]['price_inc_vat']);
    a.push(data.average_price_inc_vat);
    graphContainer.innerHTML = '';
    makeGraph(graphContainer, x, y, a, c, electric_cost, standard_tariff);
}
function makeGraph(graphContainer, x, y, a, c, electric_cost, standard_tariff){
    var traces = [{
        x: x,
        y: y,
        "line": {"shape": "hv"},
        "meta": {"columnNames": {"x": "Time", "y": "Rate"}},
        "hovertemplate": 'Price:&nbsp;%{y:.2f}<extra></extra>',
        "mode": "lines",
        "name": "Price",
    },{
        x: x,
        y: a,
        "mode": "lines",
        "name": "Average",
        "hovertemplate": 'Average Price:&nbsp;%{y:.2f}<extra></extra>',
        "line": {
            "dash": "dot",
        }  
    },{
        x: x,
        y: standard_tariff,
        "mode": "lines",
        "name": "Standard Tariff",
        "hovertemplate": 'Standard Tariff Price:&nbsp;%{y:.2f}<extra></extra>',
        "line": {
            "dash": "dot",
        },
        marker: {
            color: 'rgba(255, 0, 0, 1)'
        }
    },{
        x: x,
        y: c,
        "customdata": electric_cost,
        "type": "bar",
        "mode": "lines",
        "name": "Electric",
        "hovertemplate": 'Electric:&nbsp;%{y:.2f}kWh<br>Cost:&nbsp;£%{customdata:.2f}<extra></extra>',
        "yaxis": "y2",
        "offset": 0.25,
        "width": 0.50,
        marker: {
            color: 'rgba(44, 160, 44, 1)'
        }
    }
//,{
//  x: x,
//  y: g,
//"customdata": gas_cost,
//"type": "bar",
//"mode": "lines",
//"name": "Gas",
//"hovertemplate": 'Gas:&nbsp;%{y:.2f}kWh<br>Cost:&nbsp;£%{customdata:.2f}<extra></extra>',
//"yaxis": "y2",
//"offset": 0.25,
//"width": 0.50,
//marker: {
//    color: 'rgba(254, 220, 86, 1)' // Specify your desired color here
// }
//}
];

    Plotly.newPlot(graphContainer.id, {
        data: traces,
        layout: {
            width: graphContainer.offsetWidth,
            height: graphContainer.offsetHeight,
            margin: {"l":40, "r":40, "b": 50},
            title: "",
            barmode: 'stack',
            dragmode: false,
            "xaxis": {"type": "category", "tickvals": x, "tickangle": -90},
            "yaxis": {"type": "linear", "range": [roundDownToNearestTen(getMinOrZeroOfArray(traces[0].y)), roundUpToNearestTen(getMaxOfArray(traces[0].y))], "title": "Pence"},
            "yaxis2": {"side": "right", "type": "linear", "range": [0, (Math.ceil(getMaxOfArray(traces[3].y))+1)], "title": "kWh", "zeroline": false, "showgrid": false, "overlaying": "y"},
            "legend": {"yanchor": "top", "y": 1.3, "xanchor": "left", "x": 0, "orientation": "h"},
        },
        config: {displayModeBar: false}
    });
}
function updateCardData(data){
    e = document.getElementById('electricity_total_consumption');
    if (e && "electricity_total_consumption" in data){
        e.innerHTML = data.electricity_total_consumption + ' kWh';
    }
    e = document.getElementById('electricity_total_cost');
    if (e && "electricity_total_cost" in data){
        e.innerHTML = '£' + (data.electricity_total_cost / 100.0).toFixed(2);
    }
    e = document.getElementById('electricity_total_plunge_consumption');
    if (e && "electricity_total_plunge_consumption" in data && "electricity_total_plunge_cost" in data){
        e.innerHTML = (data.electricity_total_plunge_consumption).toFixed(3) + ' kWh<br><span>£' + (data.electricity_total_plunge_cost / 100.0).toFixed(2) + '</span>';
    }
    e = document.getElementById('electricity_consumption_below_average');
    if (e && "electricity_consumption_below_average" in data){
        e.innerHTML = data.electricity_consumption_below_average + '%';
    }
    e = document.getElementById('electricity_compare_standard_tariff');
    if (e && "electricity_compare_standard_tariff" in data && "electricity_standard_tariff" in data){
        e.innerHTML = '£' + ((data.electricity_standard_tariff[0].value_inc_vat / 100.0) * data.electricity_total_consumption).toFixed(2);
    }
}


document.addEventListener('DOMContentLoaded', async function() {
    // Fetch data from API
    const response = await fetch('/get/dashboard_data?date='+document.getElementById('current_date').value);
    if (!response.ok) {
        showNotification(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();

    updateCardData(data);
    // getPriceUsageGraph();
    makeDashboardGraph(data);
    getPriceList(data);
});

