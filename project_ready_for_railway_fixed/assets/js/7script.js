// assets/js/script.js

// Today's expiry check and sound alert
function checkTodaysExpiry() {
    fetch('api_check_expiry.php') // We will create an API endpoint
        .then(response => response.json())
        .then(data => {
            if (data.expiredToday > 0) {
                const alertSound = document.getElementById('alert-sound');
                if(alertSound) {
                    alertSound.play();
                }
                // Voice alert (if browser supports it)
                if ('speechSynthesis' in window) {
                    const msg = new SpeechSynthesisUtterance(`${data.expiredToday} items are expiring today.`);
                    msg.lang = 'en-US'; // English voice
                    window.speechSynthesis.speak(msg);
                } else {
                    alert(`${data.expiredToday} items are expiring today.`);
                }
            }
        })
        .catch(error => console.error('Error checking expiry:', error));
}
