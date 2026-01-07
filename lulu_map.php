<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=Please login first');
    exit;
}
require 'db.php';

// Get Lulu Mall ID
$stmt = $pdo->prepare("SELECT id FROM parking_lots WHERE name = ?");
$stmt->execute(['Lulu Mall']);
$lulu_lot = $stmt->fetch();
if (!$lulu_lot) {
    die("Lulu Mall not found in database");
}
$lot_id = $lulu_lot['id'];

include 'includes/header.php';
// Note: We include header for consistent nav, but we might override styles for the map container
?>

<style>
    /* Apple-Inspired Premium Aesthetics */
    :root {
        /* Apple System Colors (Dark Mode) */
        --apple-green: #30D158;
        --apple-red: #FF453A;
        --apple-blue: #0A84FF;
        --apple-gray: #8E8E93;
        --bg-dark: #000000;
        --card-bg: rgba(28, 28, 30, 0.65);
        --text-primary: #FFFFFF;
        --text-secondary: #EBEBF599; /* ~60% white */
        
        /* Glassmorphism */
        --glass-blur: blur(25px);
        --border-light: rgba(255, 255, 255, 0.1);
        --shadow-soft: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
    }

    body {
        margin: 0;
        background-color: var(--bg-dark);
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: var(--text-primary);
        font-smoothing: antialiased;
        -webkit-font-smoothing: antialiased;
    }

    /* Layout Container */
    .map-container {
        display: flex;
        flex-direction: column;
        height: 100vh;
        width: 100vw;
        padding: 20px;
        box-sizing: border-box;
        gap: 20px;
        background: radial-gradient(circle at top right, #1c1c1e 0%, #000000 100%);
    }

    /* Top Stats Bar */
    .stats-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--card-bg);
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur); /* Safari */
        padding: 20px 30px;
        border-radius: 24px;
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-soft);
        z-index: 10;
    }

    h1 {
        font-size: 24px;
        font-weight: 600;
        letter-spacing: -0.5px;
        margin: 0;
        color: var(--text-primary);
    }

    #current-floor-badge {
        background: rgba(10, 132, 255, 0.2);
        color: var(--apple-blue);
        padding: 4px 12px;
        border-radius: 100px;
        font-size: 14px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .stat-box {
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 80px;
    }
    .stat-label {
        font-size: 11px;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
        margin-bottom: 4px;
    }
    .stat-value {
        font-size: 28px;
        font-weight: 500; /* Apple prefers slightly lighter weights for large numbers */
        letter-spacing: -1px;
    }
    .stat-value.green { color: var(--apple-green); }
    .stat-value.red { color: var(--apple-red); }

    /* Main Content Area */
    .content-area {
        display: flex;
        flex: 1;
        gap: 20px;
        overflow: hidden;
    }

    /* Floor Selector (Left Sidebar) */
    .floor-selector {
        width: 90px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: var(--card-bg);
        backdrop-filter: var(--glass-blur);
        padding: 20px 10px;
        border-radius: 24px;
        align-items: center;
        border: 1px solid var(--border-light);
        max-height: calc(100vh - 250px);
        overflow-y: auto;
        /* Hide scrollbar but allow scroll */
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .floor-selector::-webkit-scrollbar {
        display: none;
    }

    .floor-btn {
        width: 60px;
        height: 60px;
        border-radius: 18px; /* Squircle-ish */
        border: none;
        background: rgba(255,255,255,0.08);
        color: var(--text-secondary);
        font-weight: 500;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.25, 1, 0.5, 1);
    }

    .floor-btn:hover {
        background: rgba(255,255,255,0.15);
        color: var(--text-primary);
        transform: scale(1.02);
    }

    .floor-btn.active {
        background: var(--apple-blue);
        color: white;
        box-shadow: 0 4px 12px rgba(10, 132, 255, 0.4);
        font-weight: 600;
    }

    /* Map View (Right) */
    .map-view {
        flex: 1;
        background: var(--card-bg);
        backdrop-filter: var(--glass-blur);
        border: 1px solid var(--border-light);
        border-radius: 24px;
        padding: 40px;
        position: relative;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        box-shadow: inset 0 0 100px rgba(0,0,0,0.2); /* Deepen center */
    }

    /* Minimal Grid Background */
    .bay-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 24px;
        width: 100%;
        max-width: 1100px;
        /* Subtle dot pattern */
        background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
        background-size: 30px 30px;
        padding: 40px;
    }

    /* Slot Card Design */
    .slot {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        height: 140px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: space-between;
        padding: 16px;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        cursor: default;
    }

    .slot-id {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-secondary);
        opacity: 0.8;
    }

    /* Indicator (Pill/Car) */
    .indicator {
        width: 40px;
        height: 70px;
        border-radius: 8px;
        transition: all 0.4s ease;
        position: relative;
    }

    /* Available State */
    .slot.available {
        background: rgba(48, 209, 88, 0.03); /* Tiny green tint */
        border-color: rgba(48, 209, 88, 0.2);
    }
    .slot.available:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(48, 209, 88, 0.15);
        border-color: var(--apple-green);
    }
    .slot.available .indicator {
        background: transparent;
        border: 2px solid var(--apple-green);
        box-shadow: 0 0 12px rgba(48, 209, 88, 0.2);
    }
    /* "Free" Text inside pill */
    .slot.available .indicator::after {
        content: 'P';
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        font-size: 18px;
        font-weight: 700;
        color: var(--apple-green);
    }

    /* Occupied State */
    .slot.occupied {
        background: rgba(255, 69, 58, 0.03);
        border-color: rgba(255, 69, 58, 0.2);
        opacity: 0.8;
    }
    .slot.occupied .indicator {
        background: var(--apple-red);
        box-shadow: 0 4px 12px rgba(255, 69, 58, 0.4);
    }
    /* Car Roof details */
    .slot.occupied .indicator::before {
        content: '';
        position: absolute;
        top: 15%; left: 15%; right: 15%; bottom: 35%;
        background: rgba(0,0,0,0.2); /* Windshield/Roof */
        border-radius: 4px;
    }

    /* Maintenance */
    .slot.maintenance {
        border: 1px dashed var(--apple-gray);
        opacity: 0.4;
    }

    /* Scrollbar Polish */
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }

    /* Nav Arrows (Subtle) */
    .nav-arrow {
        position: absolute;
        color: var(--apple-blue);
        font-size: 40px;
        opacity: 0.2;
        transition: opacity 0.5s;
    }
    .nav-arrow:hover { opacity: 0.8; }

    /* Booking Time Selector */
    .time-selector {
        background: var(--card-bg);
        backdrop-filter: var(--glass-blur);
        padding: 20px 30px;
        border-radius: 24px;
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-soft);
        display: flex;
        gap: 20px;
        align-items: center;
        flex-wrap: wrap;
    }

    .time-input-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .time-input-group label {
        font-size: 11px;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
    }

    .time-input-group input {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid var(--border-light);
        border-radius: 12px;
        padding: 10px 16px;
        color: var(--text-primary);
        font-size: 14px;
        font-family: inherit;
        transition: all 0.2s;
    }

    .time-input-group input:focus {
        outline: none;
        border-color: var(--apple-blue);
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.1);
    }

    .update-btn {
        background: var(--apple-blue);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 12px 24px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        align-self: flex-end;
    }

    .update-btn:hover {
        background: #0066CC;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(10, 132, 255, 0.3);
    }

    /* Clickable slots */
    .slot.available {
        cursor: pointer;
    }

    /* Booking Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(10px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
        background: var(--card-bg);
        backdrop-filter: var(--glass-blur);
        border: 1px solid var(--border-light);
        border-radius: 24px;
        padding: 40px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-header {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 24px;
        color: var(--text-primary);
    }

    .modal-body {
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: 24px;
    }

    .booking-detail {
        background: rgba(255, 255, 255, 0.05);
        padding: 12px 16px;
        border-radius: 12px;
        margin: 8px 0;
        display: flex;
        justify-content: space-between;
    }

    .booking-detail-label {
        color: var(--text-secondary);
        font-size: 14px;
    }

    .booking-detail-value {
        color: var(--text-primary);
        font-weight: 600;
        font-size: 14px;
    }

    .modal-actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
    }

    .modal-btn {
        flex: 1;
        padding: 14px 24px;
        border-radius: 12px;
        border: none;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .modal-btn-primary {
        background: var(--apple-blue);
        color: white;
    }

    .modal-btn-primary:hover {
        background: #0066CC;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(10, 132, 255, 0.3);
    }

    .modal-btn-secondary {
        background: rgba(255, 255, 255, 0.08);
        color: var(--text-primary);
    }

    .modal-btn-secondary:hover {
        background: rgba(255, 255, 255, 0.15);
    }

    /* Close Button */
    .close-map-btn {
        position: fixed;
        top: 20px;
        right: 20px;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(20px);
        border: 1px solid var(--border-light);
        color: var(--text-primary);
        font-size: 24px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        z-index: 100;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .close-map-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
    }

    .close-map-btn:active {
        transform: scale(0.95);
    }

</style>


<!-- Close Button -->
<button class="close-map-btn" onclick="window.history.back()" title="Close Map">×</button>

<div class="map-container">
    
    <!-- Stats Header -->
    <div class="stats-bar">
        <div style="display:flex; align-items:center; gap:15px;">
            <h1 style="margin:0; font-size:1.8em; background: linear-gradient(to right, #fff, #aaa); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Lulu Mall Parking</h1>
            <span class="badge" id="current-floor-badge" style="background:var(--neon-blue); color:black; padding:2px 10px; border-radius:4px; font-weight:bold;">G</span>
        </div>
        
        <div style="display:flex; gap: 40px;">
            <div class="stat-box">
                <div class="stat-label">Available</div>
                <div class="stat-value green" id="stat-available">--</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Occupied</div>
                <div class="stat-value red" id="stat-occupied">--</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total</div>
                <div class="stat-value" id="stat-total">--</div>
            </div>
        </div>
    </div>

    <!-- Time Selector -->
    <div class="time-selector">
        <div style="flex: 1; display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
            <div class="time-input-group">
                <label>Start Time</label>
                <input type="datetime-local" id="start-time" 
                       min="<?php echo date('Y-m-d\TH:i'); ?>" 
                       value="<?php echo date('Y-m-d\TH:i', strtotime('+10 minutes')); ?>">
            </div>
            <div class="time-input-group">
                <label>End Time</label>
                <input type="datetime-local" id="end-time" 
                       min="<?php echo date('Y-m-d\TH:i'); ?>" 
                       value="<?php echo date('Y-m-d\TH:i', strtotime('+1 hour 10 minutes')); ?>">
            </div>
            <button class="update-btn" onclick="updateAvailability()">Update Availability</button>
        </div>
        <div style="font-size: 12px; color: var(--text-secondary); width: 100%; margin-top: 8px;">
            💡 Select your parking time, then click any <span style="color: var(--apple-green); font-weight: 600;">green slot</span> to book instantly
        </div>
    </div>

    <div class="content-area">
        <!-- Floor Selection -->
        <div class="floor-selector" id="floor-list">
            <button class="floor-btn" onclick="switchFloor('L3')">L3</button>
            <button class="floor-btn" onclick="switchFloor('L2')">L2</button>
            <button class="floor-btn" onclick="switchFloor('L1')">L1</button>
            <button class="floor-btn active" onclick="switchFloor('G')">G</button>
            <button class="floor-btn" onclick="switchFloor('B1')">B1</button>
        </div>

        <!-- Map -->
        <div class="map-view" id="map-area">
            <!-- Dynamic Injection -->
            <div style="color:white; margin-top:50px;">Loading Map Data...</div>
        </div>
    </div>

</div>

<!-- Booking Confirmation Modal -->
<div class="modal-overlay" id="booking-modal">
    <div class="modal-content">
        <div class="modal-header">Confirm Booking</div>
        <div class="modal-body">
            <p>You're about to book the following parking slot:</p>
            <div class="booking-detail">
                <span class="booking-detail-label">Slot Number</span>
                <span class="booking-detail-value" id="modal-slot-number">--</span>
            </div>
            <div class="booking-detail">
                <span class="booking-detail-label">Floor</span>
                <span class="booking-detail-value" id="modal-floor">--</span>
            </div>
            <div class="booking-detail">
                <span class="booking-detail-label">Start Time</span>
                <span class="booking-detail-value" id="modal-start-time">--</span>
            </div>
            <div class="booking-detail">
                <span class="booking-detail-label">End Time</span>
                <span class="booking-detail-value" id="modal-end-time">--</span>
            </div>
            <div class="booking-detail">
                <span class="booking-detail-label">Duration</span>
                <span class="booking-detail-value" id="modal-duration">--</span>
            </div>
        </div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-secondary" onclick="closeBookingModal()">Cancel</button>
            <button class="modal-btn modal-btn-primary" onclick="confirmBooking()">Confirm Booking</button>
        </div>
    </div>
</div>

<script>
let currentFloor = 'G';
let allData = {};
let selectedSlot = null;
let bookingTimes = {
    start: '',
    end: ''
};

document.addEventListener('DOMContentLoaded', () => {
    updateBookingTimes();
    fetchData();
    setInterval(fetchData, 5000); // Live update every 5s
});

function updateBookingTimes() {
    bookingTimes.start = document.getElementById('start-time').value;
    bookingTimes.end = document.getElementById('end-time').value;
}

async function updateAvailability() {
    updateBookingTimes();
    
    if (!bookingTimes.start || !bookingTimes.end) {
        alert('Please select both start and end times');
        return;
    }
    
    if (new Date(bookingTimes.start) >= new Date(bookingTimes.end)) {
        alert('End time must be after start time');
        return;
    }
    
    await fetchData();
}

async function fetchData() {
    try {
        updateBookingTimes();
        const params = new URLSearchParams({
            start_time: bookingTimes.start,
            end_time: bookingTimes.end
        });
        
        const res = await fetch(`api_get_slots_booking.php?${params}`);
        const data = await res.json();
        
        if(data.status === 'success') {
            allData = data;
            renderMap();
            updateStats();
        }
    } catch(e) {
        console.error("Fetch error", e);
    }
}

function switchFloor(floor) {
    currentFloor = floor;
    
    // Update buttons
    document.querySelectorAll('.floor-btn').forEach(btn => {
        if(btn.innerText === floor) btn.classList.add('active');
        else btn.classList.remove('active');
    });

    document.getElementById('current-floor-badge').innerText = floor;
    
    renderMap();
    updateStats();
}

function updateStats() {
    const floorStats = allData.summary && allData.summary[currentFloor];
    if(floorStats) {
        document.getElementById('stat-available').innerText = floorStats.available;
        document.getElementById('stat-occupied').innerText = floorStats.occupied;
        document.getElementById('stat-total').innerText = floorStats.total;
    } else {
        document.getElementById('stat-available').innerText = '0';
        document.getElementById('stat-occupied').innerText = '0';
        document.getElementById('stat-total').innerText = '0';
    }
}

function renderMap() {
    const container = document.getElementById('map-area');
    const slots = (allData.floors && allData.floors[currentFloor]) || [];
    
    if(slots.length === 0) {
        container.innerHTML = '<div style="color:#aaa; margin-top:50px; font-size:1.2em;">No slots found for this level.</div>';
        return;
    }

    let html = '<div class="bay-grid">';
    
    // Navigation Arrow (Fake visual)
    html += '<div class="nav-arrow" style="top:50%; left:20px;">&#10140;</div>';
    html += '<div class="nav-arrow" style="top:50%; right:20px; transform:rotate(180deg)">&#10140;</div>';

    slots.forEach(slot => {
        const isAvailable = slot.is_available === true || slot.is_available === 1;
        const isMaint = parseInt(slot.is_maintenance) === 1;
        
        let statusClass = 'available';
        if (isMaint) statusClass = 'maintenance';
        else if (!isAvailable) statusClass = 'occupied';

        const clickHandler = isAvailable ? `onclick="openBookingModal(${slot.id}, '${slot.slot_number}')"` : '';

        html += `
            <div class="slot ${statusClass}" id="slot-${slot.id}" ${clickHandler}>
                <div class="slot-id">${slot.slot_number}</div>
                <div class="indicator"></div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
}

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
</script>

<?php include 'includes/footer.php'; ?>
