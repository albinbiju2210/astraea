import time
import random
import sys
import traceback
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE_URL = "http://localhost/astraea"

class AstraeaTester:
    def __init__(self):
        self.log("INFO", "Initializing Chrome WebDriver...")
        options = webdriver.ChromeOptions()
        # options.add_argument('--headless') # Uncomment to run silently without opening a browser window
        options.add_experimental_option('excludeSwitches', ['enable-logging'])
        
        self.driver = webdriver.Chrome(options=options)
        self.driver.maximize_window()
        self.wait = WebDriverWait(self.driver, 10)
        
        # Generate random unique data for this test run to prevent duplicate db errors
        self.test_phone = f"99{random.randint(10000000, 99999999)}"
        self.test_email = f"test_{self.test_phone}@astraea.local"
        self.test_password = "password123"
        self.test_name = "Auto Tester"
        
        # Fixed admin credentials (assumes default setup)
        self.admin_email = "admin@astraea.com"
        self.admin_password = "admin"
        
        self.passed = 0
        self.failed = 0

    def log(self, status, message):
        icons = {"INFO": "[INFO]", "SUCCESS": "[PASS]", "ERROR": "[FAIL]", "WARN": "[WARN]", "STEP": " [->]"}
        icon = icons.get(status, "->")
        print(f"[{time.strftime('%H:%M:%S')}] {icon} {message}")
        if status == "ERROR":
            self.failed += 1
        elif status == "SUCCESS":
            self.passed += 1

    def run_tests(self):
        self.log("INFO", f"Starting Test Suite. Target URL: {BASE_URL}")
        self.log("INFO", f"Generated Test User: {self.test_phone} / {self.test_email}")
        print("-" * 60)
        
        try:
            self.test_homepage()
            self.test_registration()
            self.test_user_login()
            self.test_booking_dashboard()
            # self.test_admin_login() # Uncomment and configure self.admin_email to test admin mode
        except Exception as e:
            self.log("ERROR", f"CRITICAL SUITE FAILURE: {str(e)}")
            
        print("-" * 60)
        self.log("INFO", f"Test Summary: {self.passed} Passed, {self.failed} Failed.")
        if self.failed > 0:
            sys.exit(1)

    def test_homepage(self):
        self.log("STEP", "Testing Homepage rendering...")
        try:
            self.driver.get(BASE_URL)
            assert "Astraea" in self.driver.title
            self.log("SUCCESS", "Homepage loaded successfully.")
        except AssertionError:
            self.log("ERROR", "Homepage title did not match expected 'Astraea'.")

    def test_registration(self):
        self.log("STEP", "Testing User Registration flow...")
        try:
            self.driver.get(f"{BASE_URL}/register.php")
            
            # Form Filling
            self.wait.until(EC.presence_of_element_located((By.NAME, "name"))).send_keys(self.test_name)
            self.driver.find_element(By.NAME, "email").send_keys(self.test_email)
            self.driver.find_element(By.NAME, "phone").send_keys(self.test_phone)
            self.driver.find_element(By.NAME, "password").send_keys(self.test_password)
            self.driver.find_element(By.NAME, "password_confirm").send_keys(self.test_password)
            
            self.driver.find_element(By.XPATH, "//button[@type='submit']").click()
            
            # Wait for success message element
            self.wait.until(EC.presence_of_element_located((By.CLASS_NAME, "msg-success")))
            self.log("SUCCESS", f"User registered successfully ({self.test_phone}).")
        except Exception as e:
            self.log("ERROR", f"Registration test failed:\n{traceback.format_exc()}")

    def test_user_login(self):
        self.log("STEP", "Testing User Login flow...")
        try:
            self.driver.get(f"{BASE_URL}/index.php")
            
            # Use Email login (default visible tab)
            email_input = self.wait.until(EC.element_to_be_clickable((By.NAME, "email")))
            email_input.send_keys(self.test_email)
            self.driver.find_element(By.NAME, "password").send_keys(self.test_password)
            
            self.driver.find_element(By.XPATH, "//button[@type='submit']").click()
            
            # Expected to redirect to home.php on success
            self.wait.until(EC.url_contains("home.php"))
            self.log("SUCCESS", "User login successful and redirected to dashboard.")
        except Exception as e:
            self.log("ERROR", f"User login test failed:\n{traceback.format_exc()}")

    def test_booking_dashboard(self):
        self.log("STEP", "Testing Search & Booking layout rendering...")
        try:
            # Assumes user is already logged in from previous test
            self.driver.get(f"{BASE_URL}/booking.php")
            
            # Step 1: Click the first parking lot
            lot_link = self.wait.until(EC.element_to_be_clickable((By.XPATH, "//a[contains(@href, 'lot_id')]")))
            lot_link.click()
            
            # Step 2: Enter vehicle number and click check availability
            v_input = self.wait.until(EC.presence_of_element_located((By.NAME, "vehicle_number")))
            v_input.clear()
            v_input.send_keys("KL-TEST-123")
            self.driver.find_element(By.XPATH, "//button[contains(text(), 'Check Availability')]").click()
            
            # Step 3: Wait for map to load (look for an OPEN slot button or the Change button)
            self.wait.until(EC.presence_of_element_located((By.XPATH, "//a[contains(text(), 'Change')]")))
            self.log("SUCCESS", "Booking steps rendered successfully up to the map view.")
                
        except Exception as e:
            self.log("ERROR", f"Search/Booking layout test failed:\n{traceback.format_exc()}")

    def test_admin_login(self):
        self.log("STEP", "Testing Admin Login flow...")
        try:
            # Logout user first by hitting logout endpoint
            self.driver.get(f"{BASE_URL}/logout.php")
            
            self.driver.get(f"{BASE_URL}/admin_login.php")
            
            email_input = self.wait.until(EC.presence_of_element_located((By.NAME, "email")))
            email_input.send_keys(self.admin_email)
            self.driver.find_element(By.NAME, "password").send_keys(self.admin_password)
            
            self.driver.find_element(By.XPATH, "//button[@type='submit']").click()
            
            # Expected to redirect to admin_home.php on success
            self.wait.until(EC.url_contains("admin_home.php"))
            self.log("SUCCESS", "Admin login successful and redirected to admin dashboard.")
            
        except Exception as e:
            self.log("ERROR", f"Admin login test failed:\n{traceback.format_exc()}")

    def teardown(self):
        self.log("INFO", "Tearing down WebDriver...")
        try:
            self.driver.quit()
        except:
            pass

if __name__ == "__main__":
    tester = AstraeaTester()
    try:
        tester.run_tests()
    finally:
        tester.teardown()
