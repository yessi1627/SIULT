import os
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

BASE_URL = os.getenv("SISTEMA_ESCOLAR_URL", "http://localhost/proyectoGestorEscolar")
MATERIA_MATRICULADA = os.getenv("MATERIA_MATRICULADA", "LENGUAJE")

# Este caso requiere un estudiante matriculado en una sola de las tres materias
# con al menos una tarea creada en cada materia.
driver = webdriver.Chrome()
try:
    driver.get(f"{BASE_URL}/index.php")
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "email")))
    driver.find_element(By.NAME, "email").send_keys("estudiante@gmail.com")
    driver.find_element(By.NAME, "password").send_keys("123", Keys.RETURN)
    WebDriverWait(driver, 10).until(lambda browser: "/admin/" in browser.current_url)

    driver.get(f"{BASE_URL}/admin/tareas/index.php")
    WebDriverWait(driver, 10).until(
        EC.presence_of_element_located((By.CSS_SELECTOR, "#example1 tbody"))
    )
    filas = driver.find_elements(By.CSS_SELECTOR, "#example1 tbody tr")
    materias_visibles = {
        fila.find_elements(By.TAG_NAME, "td")[6].text.strip()
        for fila in filas
        if len(fila.find_elements(By.TAG_NAME, "td")) >= 7
    }
    driver.save_screenshot("evidencia_filtro_matriculas.png")

    assert materias_visibles == {MATERIA_MATRICULADA}, (
        f"Se esperaban tareas solo de {MATERIA_MATRICULADA}; "
        f"se encontraron: {sorted(materias_visibles)}"
    )
    print(
        "PASÓ: estudiante matriculado solo en una materia; "
        f"materias visibles: {sorted(materias_visibles)}"
    )
finally:
    driver.quit()
