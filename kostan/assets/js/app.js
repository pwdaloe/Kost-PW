// Format angka menjadi Rupiah (tanpa "Rp", hanya angka terformat)
function formatNumber(val) {
  return parseInt(String(val).replace(/\D/g, '') || 0, 10)
    .toLocaleString('id-ID');
}

// Auto-format input rupiah saat mengetik
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[inputmode="numeric"]').forEach(input => {
    input.addEventListener('input', () => {
      const raw = input.value.replace(/\D/g, '');
      input.value = raw ? parseInt(raw, 10).toLocaleString('id-ID') : '';
    });
  });

  // Auto-dismiss alert setelah 5 detik
  document.querySelectorAll('.alert.alert-success').forEach(el => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      if (bsAlert) bsAlert.close();
    }, 5000);
  });
});
