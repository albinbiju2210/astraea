import time
import random
import sys
import traceback
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE_URL = "http://localhost/astraea"

class LoginTester:
    def __init__(self):
        self.log("INFO", "Initializing Chrome WebDriver...")
        options = webdriver.ChromeOptions()
        # options.add_argument('--headless') # Uncomment to run silently without opening a browser window
        options.add_experimental_option('excludeSwitches', ['enable-logging'])
        
        self.driver = webdriver.Chrome(options=options)
        self.driver.maximize_window()
        self.wait = WebDriverWait(self.driver, 10)
        
        # Generate random unique data for this test run
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

    def setup_user(self):
        self.log("INFO", "Setting up a test user for login test...")
        self.driver.get(f"{BASE_URL}/register.php")
        self.wait.until(EC.presence_of_element_located((By.NAME, "name"))).send_keys(self.test_name)
        self.driver.find_element(By.NAME, "email").send_keys(self.test_email)
        self.driver.find_element(By.NAME, "phone").send_keys(self.test_phone)
        self.driver.find_element(By.NAME, "password").send_keys(self.test_password)
        self.driver.find_element(By.NAME, "password_confirm").send_keys(self.test_password)
        self.driver.find_element(By.XPATH, "//button[@type='submit']").click()
        self.wait.until(EC.presence_of_element_located((By.CLASS_NAME, "msg-success")))

    def run_tests(self):
        self.log("INFO", f"Starting Login Test Suite. Target URL: {BASE_URL}")
        self.log("INFO", f"Generated Test User: {self.test_phone} / {self.test_email}")
        print("-" * 60)
        
        try:
            self.setup_user()
            self.test_user_login()
        except Exception as e:
            self.log("ERROR", f"CRITICAL SUITE FAILURE: {str(e)}")
            
        print("-" * 60)
        self.log("INFO", f"Test Summary: {self.passed} Passed, {self.failed} Failed.")
        if self.failed > 0:
            sys.exit(1)

    def test_user_login(self):
        self.log("STEP", "Testing User Login flow...")
        try:
            self.driver.get(f"{BASE_URL}/index.php")
            
            # Use Email login
            email_input = self.wait.until(EC.element_to_be_clickable((By.NAME, "email")))
            email_input.send_keys(self.test_email)
            self.driver.find_element(By.NAME, "password").send_keys(self.test_password)
            
            self.driver.find_element(By.XPATH, "//button[@type='submit']").click()
            
            # Expected to redirect to home.php on success
            self.wait.until(EC.url_contains("home.php"))
            self.log("SUCCESS", "User login successful and redirected to dashboard.")
        except Exception as e:
            self.log("ERROR", f"User login test failed:\n{traceback.format_exc()}")

    def teardown(self):
        self.log("INFO", "Tearing down WebDriver...")
        try:
            self.driver.quit()
        except:
            pass

if __name__ == "__main__":
    tester = LoginTester()
    try:
        tester.run_tests()
    finally:
        tester.teardown()
