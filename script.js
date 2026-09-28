const botaoMenu = document.getElementById('abrir-menu');
const menu = document.getElementById('menu');

if (botaoMenu && menu) {
    botaoMenu.addEventListener('click', () => menu.classList.toggle('aberto'));
    document.addEventListener('click', (evento) => {
        if (!menu.contains(evento.target) && evento.target !== botaoMenu) {
            menu.classList.remove('aberto');
        }
    });
}
