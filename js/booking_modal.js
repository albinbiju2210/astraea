function openBookingModal(slotId, slotNumber) {
    selectedSlot = { id: slotId, number: slotNumber };

    document.getElementById('modal-slot-number').innerText = slotNumber;
    document.getElementById('modal-floor').innerText = currentFloor;

    const startTime = new Date(bookingTimes.start);
    const endTime = new Date(bookingTimes.end);

    document.getElementById('modal-start-time').innerText = formatDateTime(startTime);
    document.getElementById('modal-end-time').innerText = formatDateTime(endTime);

    const durationMs = endTime - startTime;
    const hours = Math.floor(durationMs / (1000 * 60 * 60));
    const minutes = Math.floor((durationMs % (1000 * 60 * 60)) / (1000 * 60));
    document.getElementById('modal-duration').innerText = `${hours}h ${minutes}m`;

    document.getElementById('booking-modal').classList.add('active');
}

function closeBookingModal() {
    document.getElementById('booking-modal').classList.remove('active');
    selectedSlot = null;
}

async function confirmBooking() {
    if (!selectedSlot) return;

    try {
        const formData = new FormData();
        formData.append('action', 'book_slot');
        formData.append('slot_id', selectedSlot.id);
        formData.append('lot_id', '<?php echo $lot_id; ?>');
        formData.append('start_time', bookingTimes.start);
        formData.append('end_time', bookingTimes.end);

        const response = await fetch('process_booking.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert('✅ Booking confirmed! Redirecting to your bookings...');
            window.location.href = 'my_bookings.php?new_booking=1';
        } else {
            alert('❌ Booking failed: ' + (result.message || 'Unknown error'));
            closeBookingModal();
            fetchData();
        }
    } catch (e) {
        console.error('Booking error:', e);
        alert('❌ An error occurred while booking. Please try again.');
        closeBookingModal();
    }
}

function formatDateTime(date) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const month = months[date.getMonth()];
    const day = date.getDate();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${month} ${day}, ${hours}:${minutes}`;
}

document.addEventListener('click', (e) => {
    const modal = document.getElementById('booking-modal');
    if (e.target === modal) {
        closeBookingModal();
    }
});
