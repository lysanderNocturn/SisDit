@echo off
:: ============================================================
:: Agregar-SisDit.bat - IP Fija 10.1.85.9 IP de servidor
:: Ejecutar como administrador
:: ============================================================

echo.
echo ============================================================
echo CONFIGURACION SIS.DIT - RED LOCAL
echo ============================================================
echo.

set IP=10.1.85.9
set HOST=%SystemRoot%\System32\drivers\etc\hosts
set SHORTCUT_NAME=Sis.Dit.url
set DESKTOP=%PUBLIC%\Desktop


echo IP del servidor: %IP%
echo Nombre del host: Sis.Dit
echo.

echo Verificando si ya existe la entrada en el archivo hosts...
:: Comprobar si la entrada ya existe
findstr /C:"%IP% sis.dit" "%HOST%" >nul
if %errorlevel% == 0 (
    echo.
    echo La entrada ya existe en el archivo hosts.
    echo No se realizarán cambios en hosts.
) else (
    :: Agregar la entrada al archivo hosts
    echo >> "%HOST%"
    echo # == SisDit - Acceso Local == >> "%HOST%"
    echo %IP% sis.dit >> "%HOST%"
    echo.
    echo Entrada agregada correctamente al archivo hosts.
)

echo Creando acceso directo en el escritorio...
echo [InternetShortcut] > "%DESKTOP%\%SHORTCUT_NAME%"
echo URL=http://sis.dit >> "%DESKTOP%\%SHORTCUT_NAME%"
echo IconIndex=13 >> "%DESKTOP%\%SHORTCUT_NAME%"
echo IconFile=C:\Windows\System32\SHELL32.dll >> "%DESKTOP%\%SHORTCUT_NAME%"
echo.
echo Verificando la configuración...
ping -n 1 sis.dit >nul
if %errorlevel% == 0 (
    echo Configuración verificada correctamente. El host sis.dit responde.
) else (
    echo Error: No se pudo resolver el host sis.dit. Verifique la configuración.
)
echo.
pause
