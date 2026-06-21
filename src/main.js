import './main.css'

/* ── Navbar scroll ─────────────────────────────────── */
const navbar = document.getElementById('navbar')
window.addEventListener('scroll', () => {
  navbar?.classList.toggle('scrolled', window.scrollY > 60)
}, { passive: true })

/* ── Hamburger ─────────────────────────────────────── */
const hamburger = document.getElementById('hamburger')
const mobileNav = document.getElementById('mobile-nav')
hamburger?.addEventListener('click', () => {
  hamburger.classList.toggle('abierto')
  mobileNav?.classList.toggle('hidden')
})
document.querySelectorAll('.nav-mobile-link').forEach(link => {
  link.addEventListener('click', () => {
    hamburger?.classList.remove('abierto')
    mobileNav?.classList.add('hidden')
  })
})

/* ── Filtro galería ────────────────────────────────── */
document.querySelectorAll('.filtro').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filtro').forEach(b => b.classList.remove('active'))
    btn.classList.add('active')
    const cat = btn.dataset.filtro
    document.querySelectorAll('.galeria-item').forEach(item => {
      item.classList.toggle('oculto', cat !== 'todos' && item.dataset.categoria !== cat)
    })
  })
})

/* ── Lightbox ──────────────────────────────────────── */
const lightbox = document.createElement('div')
lightbox.id = 'lightbox'
lightbox.innerHTML = `
  <button id="lightbox-close" aria-label="Cerrar">✕</button>
  <img id="lightbox-img" src="" alt="">
  <span id="lightbox-cat"></span>
`
document.body.appendChild(lightbox)

const lbImg = document.getElementById('lightbox-img')
const lbCat = document.getElementById('lightbox-cat')

function openLightbox(src, alt, cat) {
  lbImg.src = src
  lbImg.alt = alt
  lbCat.textContent = cat
  lightbox.classList.add('visible')
  document.body.style.overflow = 'hidden'
}
function closeLightbox() {
  lightbox.classList.remove('visible')
  document.body.style.overflow = ''
}

document.querySelectorAll('.galeria-item').forEach(item => {
  item.addEventListener('click', () => {
    const img = item.querySelector('img')
    openLightbox(img.src, img.alt, item.dataset.categoria)
  })
})
document.getElementById('lightbox-close')?.addEventListener('click', closeLightbox)
lightbox.addEventListener('click', e => { if (e.target === lightbox) closeLightbox() })
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox() })

/* ── Reveal on scroll ──────────────────────────────── */
const observer = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible')
      observer.unobserve(entry.target)
    }
  })
}, { threshold: 0.12 })

document.querySelectorAll('.reveal').forEach(el => observer.observe(el))

/* ── Formulario de contacto ────────────────────────── */
const form = document.getElementById('form-contacto')
const aviso = document.getElementById('form-aviso')
const btnEnviar = document.getElementById('btn-enviar')

form?.addEventListener('submit', async e => {
  e.preventDefault()
  if (!btnEnviar || !aviso) return

  btnEnviar.disabled = true
  btnEnviar.textContent = 'Enviando…'
  aviso.className = 'hidden'

  try {
    const res = await fetch('/api/contacto.php', { method: 'POST', body: new FormData(form) })
    const json = await res.json()

    aviso.className = json.ok
      ? 'py-3 px-4 text-sm bg-green-900/30 text-green-400 border border-green-900'
      : 'py-3 px-4 text-sm bg-red-900/30 text-red-400 border border-red-900'
    aviso.textContent = json.ok
      ? '¡Mensaje enviado! Te contactaremos en menos de 24 horas.'
      : (json.error || 'Error al enviar. Inténtalo de nuevo.')
    if (json.ok) form.reset()
  } catch {
    aviso.className = 'py-3 px-4 text-sm bg-red-900/30 text-red-400 border border-red-900'
    aviso.textContent = 'No se pudo conectar con el servidor.'
  } finally {
    btnEnviar.disabled = false
    btnEnviar.textContent = 'Enviar mensaje'
  }
})
