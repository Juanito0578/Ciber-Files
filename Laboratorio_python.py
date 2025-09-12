from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager
import time

# --------------------------
# 1. Pedir ciudad
# --------------------------
ciudad = input("Introduce la ciudad: ").strip().lower().replace(" ", "-")

# --------------------------
# 2. Abrir navegador
# --------------------------
driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()))
driver.get(f"https://www.tiempo.com/{ciudad}.htm")

# --------------------------
# 3. Aceptar cookies si aparece
# --------------------------
try:
    aceptar_cookies = driver.find_element(By.ID, "btn-gdpr-accept")
    aceptar_cookies.click()
except:
    pass


# --------------------------
# 4. Función segura para extraer texto
# --------------------------
def extraer_texto(selector):
    elems = driver.find_elements(By.CSS_SELECTOR, selector)
    return elems[0].text.strip() if elems else "N/A"

# --------------------------
# 5. Extraer datos
# --------------------------
try:
    temp_element = driver.find_elements(By.CSS_SELECTOR, "span.dato-temperatura.changeUnitT")
    temperatura = temp_element[0].text.strip() if temp_element else "N/A"
    valor_temp = temp_element[0].get_attribute("data-weather").split("|")[0] if temp_element else "N/A"

    print(f"Ciudad: {ciudad}")
    print(f"Temperatura: {temperatura} (valor exacto: {valor_temp}°C)")

except Exception as e:
    print(f"[ERROR] No se pudo extraer la información: {e}")

finally:
    # --------------------------
    # Cerrar navegador
    # --------------------------
    driver.quit()
