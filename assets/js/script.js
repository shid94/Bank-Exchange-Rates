function convertCurrency(){

    let amount = parseFloat(document.getElementById('amount').value);
    let currency = document.getElementById('currency');
    let type = document.getElementById('type').value;

    if (!amount || amount <= 0) {
        document.getElementById('result').innerText = "Enter a valid amount";
        return;
    }

    let selected = currency.options[currency.selectedIndex];

    let buyRate = parseFloat(selected.value);
    let sellRate = parseFloat(selected.getAttribute('data-sell'));

    let rate, label;

    if(type === 'buy'){
        // Customer selling → bank buys → use BUY rate
        rate = buyRate;
        label = "Buy Rate";
    } else {
        // Customer buying → bank sells → use SELL rate
        rate = sellRate;
        label = "Sell Rate";
    }

    let result = amount * rate;

    document.getElementById('result').innerText =
        `Result: ${result.toFixed(2)} (Using ${label}: ${rate})`;
}