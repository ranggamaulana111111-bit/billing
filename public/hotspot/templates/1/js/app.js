```javascript
function showVoucher() {
    document.getElementById('voucherFields').style.display = 'block';
    document.getElementById('memberFields').style.display = 'none';

    document.getElementById('tv').classList.add('active');
    document.getElementById('tm').classList.remove('active');
}

function showMember() {
    document.getElementById('voucherFields').style.display = 'none';
    document.getElementById('memberFields').style.display = 'block';

    document.getElementById('tm').classList.add('active');
    document.getElementById('tv').classList.remove('active');
}
```
