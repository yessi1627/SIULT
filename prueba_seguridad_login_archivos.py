import os
import tempfile
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE_URL = os.getenv("SISTEMA_ESCOLAR_URL", "http://localhost/proyectoGestorEscolar")
TAREA_ID = os.getenv("TAREA_ID", "98")
CAPTURAS = "Capturas seguridad"
os.makedirs(CAPTURAS, exist_ok=True)
driver = webdriver.Chrome()

try:
    # Inyección SQL: debe volver al login sin mostrar errores del servidor.
    driver.get(f"{BASE_URL}/index.php")
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "email")))
    driver.execute_script(
        "document.querySelector('[name=email]').value = \"' OR '1'='1\";"
        "document.querySelector('[name=password]').value = 'cualquier-clave';"
        "document.querySelector('form').submit();"
    )
    WebDriverWait(driver, 10).until(lambda browser: "index.php" in browser.current_url)
    assert "SQLSTATE" not in driver.page_source
    assert "PDOException" not in driver.page_source
    driver.save_screenshot(os.path.join(CAPTURAS, "login_inyeccion_rechazada.png"))
    print("PASÓ: intento de inyección SQL rechazado sin error técnico")

    # Login legítimo para acceder al formulario de archivos.
    driver.find_element(By.NAME, "email").send_keys("estudiante@gmail.com")
    driver.find_element(By.NAME, "password").send_keys("123", Keys.RETURN)
    WebDriverWait(driver, 10).until(lambda browser: "/admin/" in browser.current_url)
    driver.get(f"{BASE_URL}/admin/tareas/show.php?id={TAREA_ID}")
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "archivo")))
    selector = driver.find_element(By.NAME, "archivo")

    with tempfile.NamedTemporaryFile(suffix=".exe", delete=False) as archivo_invalido:
        archivo_invalido.write(b"archivo no permitido")
        ruta_invalida = archivo_invalido.name
    selector.send_keys(ruta_invalida)
    driver.find_element(By.CSS_SELECTOR, "form button[type='submit']").click()
    WebDriverWait(driver, 10).until(lambda browser: "tareas/index.php" in browser.current_url)
    assert "Tipo de archivo no permitido" in driver.page_source
    driver.save_screenshot(os.path.join(CAPTURAS, "archivo_extension_rechazada.png"))
    os.unlink(ruta_invalida)
    print("PASÓ: extensión no permitida rechazada")

    driver.get(f"{BASE_URL}/admin/tareas/show.php?id={TAREA_ID}")
    selector = WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "archivo")))
    with tempfile.NamedTemporaryFile(suffix=".pdf", delete=False) as archivo_grande:
        archivo_grande.write(b"0" * (5 * 1024 * 1024 + 1))
        ruta_grande = archivo_grande.name
    selector.send_keys(ruta_grande)
    driver.find_element(By.CSS_SELECTOR, "form button[type='submit']").click()
    WebDriverWait(driver, 10).until(lambda browser: "tareas/index.php" in browser.current_url)
    assert "supera el tamaño máximo" in driver.page_source
    driver.save_screenshot(os.path.join(CAPTURAS, "archivo_tamano_rechazado.png"))
    os.unlink(ruta_grande)
    print("PASÓ: archivo mayor de 5 MB rechazado")
finally:
    driver.quit()
