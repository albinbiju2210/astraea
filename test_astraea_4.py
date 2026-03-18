import time
import sys
import random
import traceback
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE_URL = "http://localhost/astraea"

class AdminTester:
    def __init__(self):
        self.log("INFO", "Initializing Chrome WebDriver...")
        options = webdriver.ChromeOptions()
        # options.add_argument('--headless') # Uncomment to run silently without opening a browser window
        options.add_experimental_option('excludeSwitches', ['enable-logging'])
        
        self.driver = webdriver.Chrome(options=options)
        self.driver.maximize_window()
        self.wait = WebDriverWait(self.driver, 10)
        
        # Fixed admin credentials (assumes default setup)
        self.admin_email = "admin@example.com"
        self.admin_password = "Albin@1022"
        
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
        self.log("INFO", f"Starting Admin Test Suite. Target URL: {BASE_URL}")
        print("-" * 60)
        
        try:
            self.test_admin_login()
            self.test_gate_entry_exit()
        except Exception as e:
            self.log("ERROR", f"CRITICAL SUITE FAILURE: {str(e)}")
            
        print("-" * 60)
        self.log("INFO", f"Test Summary: {self.passed} Passed, {self.failed} Failed.")
        if self.failed > 0:
            sys.exit(1)

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

    def test_gate_entry_exit(self):
        self.log("STEP", "Testing Gate Entry & Exit flow...")
        try:
            self.driver.get(f"{BASE_URL}/admin_entry_exit.php")
            
            # Use a random vehicle number for this test
            test_vehicle = f"TEST{random.randint(1000, 9999)}"
            test_phone = f"99{random.randint(10000000, 99999999)}"
            
            # --- ENTRY ---
            access_input = self.wait.until(EC.presence_of_element_located((By.NAME, "access_code")))
            access_input.send_keys(test_vehicle)
            self.driver.find_element(By.XPATH, "//button[contains(text(), 'Validate Access')]").click()
            
            # Walk-in form
            phone_input = self.wait.until(EC.presence_of_element_located((By.NAME, "phone")))
            phone_input.send_keys(test_phone)
            self.driver.find_element(By.XPATH, "//button[contains(text(), 'Assign Slot')]").click()
            
            # Wait for success
            self.wait.until(EC.presence_of_element_located((By.XPATH, "//*[contains(text(), 'Walk-in Successful')]")))
            self.log("SUCCESS", f"Vehicle Entry recorded for {test_vehicle}.")
            
            # --- EXIT ---
            self.driver.get(f"{BASE_URL}/admin_entry_exit.php")
            access_input = self.wait.until(EC.presence_of_element_located((By.NAME, "access_code")))
            access_input.send_keys(test_vehicle)
            self.driver.find_element(By.XPATH, "//button[contains(text(), 'Validate Access')]").click()
            
            # Payment form
            payment_btn = self.wait.until(EC.presence_of_element_located((By.XPATH, "//button[contains(text(), 'Confirm CASH Collected')]")))
            payment_btn.click()
            
            # Wait for success message
            self.wait.until(EC.presence_of_element_located((By.XPATH, "//*[contains(text(), 'Payment Confirmed')]")))
            self.log("SUCCESS", f"Vehicle Exit & Payment recorded for {test_vehicle}.")
            
        except Exception as e:
            self.log("ERROR", f"Gate Entry/Exit test failed:\n{traceback.format_exc()}")
            with open("error.log", "w") as f:
                f.write(traceback.format_exc())
            self.driver.save_screenshot("screenshot.png")

    def teardown(self):
        self.log("INFO", "Tearing down WebDriver...")
        try:
            self.driver.quit()
        except:
            pass

if __name__ == "__main__":
    tester = AdminTester()
    try:
        tester.run_tests()
    finally:
        tester.teardown()
