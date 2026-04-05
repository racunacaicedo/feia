document.addEventListener('DOMContentLoaded', () => {
    // ========================================
    // Selector de idiomas
    // ========================================
    const languageSelectorButton = document.querySelector('#language-selector button');
    const languageOptions = document.querySelector('#language-options');

    if (languageSelectorButton && languageOptions) {
        languageSelectorButton.addEventListener('click', (event) => {
            event.stopPropagation();
            const isVisible = languageOptions.style.display === 'block';
            languageOptions.style.display = isVisible ? 'none' : 'block';
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('#language-selector')) {
                languageOptions.style.display = 'none';
            }
        });

        const languageItems = document.querySelectorAll('#language-options li');
        languageItems.forEach(item => {
            item.addEventListener('click', () => {
                const selectedLang = item.getAttribute('data-lang');
                alert(`Idioma cambiado a: ${item.textContent}`);
                languageOptions.style.display = 'none';
            });
        });
    }

    // ========================================
    // Configuración de redes sociales
    // ========================================
    const toggleSocialIcons = document.getElementById('toggle-social-icons');
    const socialIcons = document.querySelector('.social-icons');

    if (toggleSocialIcons && socialIcons) {
        toggleSocialIcons.addEventListener('click', () => {
            socialIcons.classList.toggle('hidden');
            toggleSocialIcons.innerHTML = socialIcons.classList.contains('hidden')
                ? '<i class="fas fa-share-alt"></i>'
                : '<i class="fas fa-times"></i>';
        });
    }

    // ========================================
    // Configuración del botón de WhatsApp
    // ========================================
    const whatsappIcon = document.querySelector('.whatsapp-icon');

    if (whatsappIcon) {
        whatsappIcon.style.cssText = `
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: fixed !important;
            bottom: 20px !important;
            right: 20px !important;
            z-index: 9999 !important;
        `;

        const whatsappIconInner = whatsappIcon.querySelector('i');
        if (whatsappIconInner) {
            whatsappIconInner.style.cssText = `
                position: static !important;
                display: block !important;
                text-align: center !important;
                line-height: 50px !important;
            `;
        }
    }

    // ========================================
    // Configuración del botón del chatbot
    // ========================================
    const chatbotIcon = document.getElementById('chatbot-icon');

    if (chatbotIcon) {
        chatbotIcon.addEventListener('click', () => {
            // Aquí puedes implementar la lógica del chatbot o mostrar un modal
            alert('Chatbot iniciado. Aquí puedes integrar tu chatbot.');
        });
    }

    // ========================================
    // Ajustar tamaño del logo según el tamaño de la pantalla
    // ========================================
    const logo = document.querySelector('.logo');
    const adjustLogoSize = () => {
        if (window.innerWidth <= 768) {
            logo.style.width = '100px';
            logo.style.height = 'auto';
        } else {
            logo.style.width = '200px';
            logo.style.height = 'auto';
        }
    };

    if (logo) {
        adjustLogoSize();
        window.addEventListener('resize', adjustLogoSize);
    }


// ========================================
// Validación y envío del formulario de suscripción al blog
// ========================================
const subscriptionForm = document.getElementById("subscription-form");

if (subscriptionForm) {
    subscriptionForm.addEventListener("submit", async function(event) {
        event.preventDefault(); // Evita que el formulario recargue la página

        let emailInput = document.getElementById("email-input");
        let email = emailInput.value.trim();

        // 1️⃣ Validar correo antes de enviarlo
        if (!validateEmail(email)) {
            alert("Por favor, ingresa un correo electrónico válido.");
            return;
        }

        try {
            // 2️⃣ Enviar la solicitud al servidor
            let response = await fetch("php/test.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ email: email })
            });

            // 3️⃣ Intentar convertir la respuesta a JSON
            let data;
            try {
                data = await response.json();
            } catch (jsonError) {
                console.error("Error al convertir la respuesta a JSON:", jsonError);
                throw new Error("El servidor devolvió una respuesta inesperada.");
            }

            // 4️⃣ Manejar la respuesta del servidor
            console.log("Respuesta del servidor:", data);
            alert(data.message); // Mostrar mensaje del servidor

            if (data.status === "success") {
                subscriptionForm.reset(); // Limpiar el formulario tras una suscripción exitosa
            }
        } catch (error) {
            console.error("Error en la solicitud:", error);
            alert("Gracias por registrase, enviaremos los artículos a su correo electrónico.");
        }
    });
}

// ========================================
// Función para validar correos electrónicos
// ========================================
function validateEmail(email) {
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailPattern.test(email);
}





    // ========================================
    // Validación y envío del formulario de contacto
    // ========================================
const contactForm = document.getElementById('contact-form');
if (contactForm) {
    contactForm.addEventListener('submit', async function (e) {
        e.preventDefault(); // Evita que el formulario se envíe de forma tradicional.
        try {
            console.log("=== PASO 1: Iniciando proceso de envío del formulario ===");
            // Crear un objeto FormData a partir del formulario.
            const formData = new FormData(this);
            console.log("=== PASO 2: Datos del formulario listos para enviar ===");
            console.log([...formData.entries()]); // Muestra los datos del formulario.

            // Realizar la solicitud fetch al archivo PHP.
            console.log("=== PASO 3: Enviando datos al servidor en '../php/contacto.php' ===");
            const response = await fetch('../php/contacto.php', {
                method: 'POST',
                body: formData,
            });

            // Verificar si la respuesta del servidor es exitosa.
            console.log("=== PASO 4: Respuesta del servidor recibida ===");
            console.log("Estado de la respuesta:", response.status, response.statusText);

            if (!response.ok) {
                console.error("=== ERROR: La respuesta del servidor no fue exitosa ===");
                throw new Error(`Error en la solicitud: ${response.status} - ${response.statusText}`);
            }

            // Parsear la respuesta JSON del servidor.
            console.log("=== PASO 5: Intentando parsear la respuesta como JSON ===");
            const textResponse = await response.text(); // Primero obtenemos la respuesta como texto.
            console.log("Respuesta del servidor (texto):", textResponse);

            let data;
            try {
                data = JSON.parse(textResponse); // Intentamos analizar la respuesta como JSON.
                console.log("=== PASO 6: Respuesta del servidor analizada como JSON ===");
                console.log("Datos recibidos del servidor:", data);
            } catch (parseError) {
                console.error("=== ERROR: La respuesta del servidor no es un JSON válido ===");
                console.error("Texto recibido:", textResponse);
                throw new Error("La respuesta del servidor no es un JSON válido.");
            }

            // Manejar la respuesta del servidor.
            if (data.error) {
                console.warn("=== ADVERTENCIA: El servidor reportó un error ===");
                console.warn("Mensaje de error del servidor:", data.error);
                alert('Error: ' + data.error);
            } else {
                console.log("=== PROCESO COMPLETADO CORRECTAMENTE ===");
                console.log("Mensaje del servidor:", data.message);
                alert(data.message);
                this.reset(); // Limpiar el formulario después de un envío exitoso.
            }
        } catch (error) {
            // Capturar y manejar errores durante el proceso.
        console.error("=== ERROR GENERAL: Se produjo un error durante el envío del formulario ===");

        if (error.code) {
            console.error("Código de error:", error.code);
        } else {
            console.error("No se encontró un código de error en el objeto error.");
        }

        console.error("Mensaje de error:", error.message);
        alert("Ocurrió un error al procesar la solicitud. Por favor, inténtalo nuevamente.");

        }
    });
}

    // ========================================
    // Menú responsivo
    // ========================================
    const menuToggle = document.querySelector('.menu-toggle');
    const navMenu = document.querySelector('header nav ul');
    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }

    // ========================================
    // Cambiar encabezado dinámicamente según la pantalla
    // ========================================
    const header = document.querySelector('.main-header');
    const checkScreenSize = () => {
        if (window.matchMedia('(max-width: 768px)').matches) {
            header.textContent = 'Fundación para la Enseñanza de la Inteligencia Artificial en América Latina';
            header.style.textAlign = 'left';
            header.style.paddingRight = '20px';
        } else {
            header.textContent = 'Fundación para la Enseñanza de la Inteligencia Artificial en América Latina';
            header.style.textAlign = 'center';
            header.style.paddingRight = '0';
        }
        header.style.color = '#ff9100';
    };

    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);

    // ========================================
    // Ocultar línea <hr> en pantallas pequeñas
    // ========================================
    const horizontalLine = document.querySelector('hr');
    const checkLineVisibility = () => {
        if (window.innerWidth <= 768) {
            if (horizontalLine) {
                horizontalLine.style.display = 'none';
            }
        } else {
            if (horizontalLine) {
                horizontalLine.style.display = '';
            }
        }
    };

    checkLineVisibility();
    window.addEventListener('resize', checkLineVisibility);

    // ========================================
    // Controlar "Ver más" y "Ver menos" en posts
    // ========================================
    const verMasBtn = document.querySelector('.vermas');
    const verMenosBtn = document.querySelector('.vermenos');
    const hiddenPosts = document.querySelectorAll('.post7.hidden, .post8.hidden, .post9.hidden');

    if (verMasBtn && verMenosBtn && hiddenPosts) {
        verMasBtn.addEventListener('click', (event) => {
            event.preventDefault();
            hiddenPosts.forEach(post => post.classList.remove('hidden'));
            verMasBtn.style.display = 'none';
            verMenosBtn.classList.remove('hidden');
        });

        verMenosBtn.addEventListener('click', (event) => {
            event.preventDefault();
            hiddenPosts.forEach(post => post.classList.add('hidden'));
            verMasBtn.style.display = 'inline-block';
            verMenosBtn.classList.add('hidden');
        });
    }

    // ========================================
    // Mostrar y ocultar filas de artículos adicionales
    // ========================================
    const moreArticlesBtn = document.getElementById('more-articles-btn');
    const lessArticlesBtn = document.getElementById('less-articles-btn');
    const additionalArticles = document.getElementById('additional-articles');

    if (moreArticlesBtn && lessArticlesBtn && additionalArticles) {
        moreArticlesBtn.addEventListener('click', () => {
            additionalArticles.classList.remove('hidden');
            moreArticlesBtn.style.display = 'none';
            lessArticlesBtn.style.display = 'inline-block';
        });

        lessArticlesBtn.addEventListener('click', () => {
            additionalArticles.classList.add('hidden');
            moreArticlesBtn.style.display = 'inline-block';
            lessArticlesBtn.style.display = 'none';
        });
    }

        // Selección de botones y tarjetas ocultas
    const showMoreBtn = document.getElementById('show-more');
    const showLessBtn = document.getElementById('show-less');
    const hiddenCards = document.querySelectorAll('.directory-card.hidden');

    if (showMoreBtn && showLessBtn && hiddenCards.length > 0) {
        // Mostrar más tarjetas al hacer clic en "Mostrar Más"
        showMoreBtn.addEventListener('click', () => {
            hiddenCards.forEach(card => card.classList.remove('hidden')); // Quita la clase 'hidden'
            showMoreBtn.classList.add('hidden'); // Oculta el botón "Mostrar Más"
            showLessBtn.classList.remove('hidden'); // Muestra el botón "Mostrar Menos"
        });

        // Ocultar tarjetas al hacer clic en "Mostrar Menos"
        showLessBtn.addEventListener('click', () => {
            hiddenCards.forEach(card => card.classList.add('hidden')); // Agrega la clase 'hidden'
            showLessBtn.classList.add('hidden'); // Oculta el botón "Mostrar Menos"
            showMoreBtn.classList.remove('hidden'); // Muestra el botón "Mostrar Más"
        });
    }

});
