/**
 * Главный JS-файл для клиентской части
 */

document.addEventListener('DOMContentLoaded', function() {

    // =====================================================
    // СЕЛЕКТОР КОЛИЧЕСТВА
    // =====================================================
    document.querySelectorAll('.quantity').forEach(function(qty) {
        const input = qty.querySelector('.quantity__input');
        const minusBtn = qty.querySelector('.quantity__btn--minus');
        const plusBtn = qty.querySelector('.quantity__btn--plus');
        const max = parseInt(input.getAttribute('max')) || 99;
        const min = parseInt(input.getAttribute('min')) || 1;

        if (minusBtn) {
            minusBtn.addEventListener('click', function() {
                let val = parseInt(input.value) || min;
                if (val > min) {
                    input.value = val - 1;
                    input.dispatchEvent(new Event('change'));
                }
            });
        }
        if (plusBtn) {
            plusBtn.addEventListener('click', function() {
                let val = parseInt(input.value) || min;
                if (val < max) {
                    input.value = val + 1;
                    input.dispatchEvent(new Event('change'));
                }
            });
        }
    });

    // =====================================================
    // АВТО-ОТПРАВКА ФОРМЫ ПРИ ИЗМЕНЕНИИ КОЛИЧЕСТВА В КОРЗИНЕ
    // =====================================================
    document.querySelectorAll('.cart-item__qty input').forEach(function(input) {
        let timer;
        input.addEventListener('change', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                input.closest('form').submit();
            }, 300);
        });
    });

    // =====================================================
    // ВАЛИДАЦИЯ ФОРМ
    // =====================================================
    document.querySelectorAll('form[data-validate]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            let valid = true;
            // Очистка предыдущих ошибок
            form.querySelectorAll('.form__error--js').forEach(el => el.remove());

            // Обязательные поля
            form.querySelectorAll('[required]').forEach(function(field) {
                if (!field.value.trim()) {
                    showError(field, 'Поле обязательно для заполнения');
                    valid = false;
                }
            });

            // Email
            form.querySelectorAll('input[type="email"]').forEach(function(field) {
                if (field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
                    showError(field, 'Введите корректный email');
                    valid = false;
                }
            });

            // Телефон
            form.querySelectorAll('input[type="tel"]').forEach(function(field) {
                const digits = field.value.replace(/\D/g, '');
                if (field.value && (digits.length < 10 || digits.length > 15)) {
                    showError(field, 'Введите корректный телефон');
                    valid = false;
                }
            });

            // Подтверждение пароля
            const password = form.querySelector('input[name="password"]');
            const confirm = form.querySelector('input[name="password_confirm"]');
            if (password && confirm && password.value !== confirm.value) {
                showError(confirm, 'Пароли не совпадают');
                valid = false;
            }

            if (!valid) e.preventDefault();
        });
    });

    function showError(field, message) {
        const error = document.createElement('div');
        error.className = 'form__error form__error--js';
        error.textContent = message;
        field.parentNode.appendChild(error);
        field.style.borderColor = 'var(--color-error)';
        field.addEventListener('input', function() {
            field.style.borderColor = '';
            const err = field.parentNode.querySelector('.form__error--js');
            if (err) err.remove();
        }, { once: true });
    }

    // =====================================================
    // МАСКА ТЕЛЕФОНА
    // =====================================================
    document.querySelectorAll('input[type="tel"]').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('8')) value = '7' + value.slice(1);
            if (!value.startsWith('7') && value.length > 0) value = '7' + value;
            
            let formatted = '';
            if (value.length > 0) formatted = '+' + value[0];
            if (value.length > 1) formatted += ' (' + value.substring(1, 4);
            if (value.length >= 4) formatted += ') ' + value.substring(4, 7);
            if (value.length >= 7) formatted += '-' + value.substring(7, 9);
            if (value.length >= 9) formatted += '-' + value.substring(9, 11);
            
            e.target.value = formatted;
        });
    });

    // =====================================================
    // ПОДТВЕРЖДЕНИЕ ДЕЙСТВИЙ
    // =====================================================
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // =====================================================
    // АВТОСКРЫТИЕ FLASH-СООБЩЕНИЙ
    // =====================================================
    document.querySelectorAll('.flash').forEach(function(flash) {
        setTimeout(function() {
            flash.style.transition = 'opacity 0.5s';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 500);
        }, 5000);
    });

    // =====================================================
    // МИНИМАЛЬНАЯ ДАТА ДОСТАВКИ — ЗАВТРА
    // =====================================================
    const deliveryDate = document.querySelector('input[name="delivery_date"]');
    if (deliveryDate) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        deliveryDate.min = tomorrow.toISOString().split('T')[0];
        if (!deliveryDate.value) {
            deliveryDate.value = tomorrow.toISOString().split('T')[0];
        }
    }
});

// =====================================================
// Современные интерактивные эффекты
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    // Прогресс скролла
    const indicator = document.createElement('div');
    indicator.className = 'scroll-indicator';
    indicator.style.width = '0%';
    document.body.appendChild(indicator);

    window.addEventListener('scroll', function() {
        const scrolled = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
        indicator.style.width = scrolled + '%';
    });

    // Плавная подсветка изображений при загрузке
    document.querySelectorAll('img').forEach(img => {
        if (img.complete) {
            img.classList.add('loaded');
        } else {
            img.addEventListener('load', () => img.classList.add('loaded'));
        }
    });

    // Эффект параллакса для hero (лёгкий)
    const hero = document.querySelector('.hero');
    if (hero) {
        window.addEventListener('scroll', function() {
            const scrolled = window.scrollY;
            if (scrolled < 600) {
                hero.style.backgroundPosition = `center ${scrolled * 0.3}px`;
            }
        });
    }

    // Intersection Observer — повторное проигрывание анимаций при скролле
    const observerOptions = { threshold: 0.1 };
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.section').forEach(el => observer.observe(el));
});
