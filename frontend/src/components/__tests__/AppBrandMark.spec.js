import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppBrandMark from '@/components/AppBrandMark.vue'

describe('AppBrandMark — el logo', () => {
  it('es una imagen del logo de la marca', () => {
    const img = mount(AppBrandMark)
    expect(img.element.tagName).toBe('IMG')
    expect(img.attributes('src')).toContain('logo-nc')
  })
})

describe('AppBrandMark — tamaño', () => {
  it('publica el alto como custom property', () => {
    // El logo es apaisado: se fija el alto y el ancho sale solo.
    const wrapper = mount(AppBrandMark, { props: { size: 56 } })
    expect(wrapper.attributes('style')).toContain('--mark-size: 56px')
  })

  it('el default es el del drawer', () => {
    expect(AppBrandMark.props.size.default).toBe(40)
  })
})

describe('AppBrandMark — resplandor', () => {
  it('es opt-in', () => {
    // Sobre superficie clara sobra; sólo hace falta sobre oscuro o fotografía.
    expect(AppBrandMark.props.glow.default).toBe(false)
    expect(mount(AppBrandMark).classes()).not.toContain('brand-mark--glow')
  })

  it('se activa con la prop', () => {
    expect(mount(AppBrandMark, { props: { glow: true } }).classes())
      .toContain('brand-mark--glow')
  })
})

describe('AppBrandMark — accesibilidad', () => {
  it('sin label es decorativo', () => {
    // El caso NORMAL: en el drawer y en el acceso el logo va pegado al nombre
    // escrito en texto. Sin aria-hidden, el lector de pantalla anuncia la
    // marca dos veces seguidas.
    const wrapper = mount(AppBrandMark)
    expect(wrapper.attributes('aria-hidden')).toBe('true')
    expect(wrapper.attributes('alt')).toBe('')
  })

  it('con label se anuncia como imagen', () => {
    // Para cuando el logo va solo, sin el nombre al lado.
    const wrapper = mount(AppBrandMark, { props: { label: 'For Kids' } })
    expect(wrapper.attributes('role')).toBe('img')
    expect(wrapper.attributes('alt')).toBe('For Kids')
    expect(wrapper.attributes('aria-hidden')).toBeUndefined()
  })
})
