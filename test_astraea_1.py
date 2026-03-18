import time
import random
import sys
import traceback
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE_URL = "http://localhost/astraea"

class RegistrationTester:
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
        self.log("INFO", f"Starting Registration Test Suite. Target URL: {BASE_URL}")
        self.log("INFO", f"Generated Test User: {self.test_phone} / {self.test_email}")
        print("-" * 60)
        
        try:
            self.test_homepage()
            self.test_registration()
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

    def teardown(self):
        self.log("INFO", "Tearing down WebDriver...")
        try:
            self.driver.quit()
        except:
            pass

if __name__ == "__main__":
    tester = RegistrationTester()
    try:
        tester.run_tests()
    finally:
        tester.teardown()
