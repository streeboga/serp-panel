import { describe, it, expect, beforeEach } from 'vitest'
import i18n from '@/i18n'

describe('i18n', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('en')
  })

  it('defaults to a known language', () => {
    expect(['en', 'ru']).toContain(i18n.language)
  })

  it('translates navigation labels in English', () => {
    expect(i18n.t('nav.dashboard')).toBe('Dashboard')
    expect(i18n.t('nav.projects')).toBe('Projects')
    expect(i18n.t('nav.classification')).toBe('Classification')
    expect(i18n.t('nav.scrapers')).toBe('Scrapers')
    expect(i18n.t('nav.schedules')).toBe('Schedules')
    expect(i18n.t('nav.settings')).toBe('Settings')
  })

  it('translates auth labels in English', () => {
    expect(i18n.t('auth.login')).toBe('Log In')
    expect(i18n.t('auth.register')).toBe('Register')
    expect(i18n.t('auth.logout')).toBe('Log out')
    expect(i18n.t('auth.email')).toBe('Email')
    expect(i18n.t('auth.password')).toBe('Password')
  })

  it('switches to Russian and translates navigation labels', async () => {
    await i18n.changeLanguage('ru')
    expect(i18n.t('nav.dashboard')).toBe('Дашборд')
    expect(i18n.t('nav.projects')).toBe('Проекты')
    expect(i18n.t('nav.classification')).toBe('Классификация')
    expect(i18n.t('nav.scrapers')).toBe('Парсеры')
    expect(i18n.t('nav.schedules')).toBe('Расписания')
    expect(i18n.t('nav.settings')).toBe('Настройки')
  })

  it('translates auth labels in Russian', async () => {
    await i18n.changeLanguage('ru')
    expect(i18n.t('auth.login')).toBe('Войти')
    expect(i18n.t('auth.register')).toBe('Регистрация')
    expect(i18n.t('auth.logout')).toBe('Выйти')
    expect(i18n.t('auth.password')).toBe('Пароль')
  })

  it('translates dashboard labels in both languages', async () => {
    expect(i18n.t('dashboard.title')).toBe('Dashboard')
    expect(i18n.t('dashboard.totalKeywords')).toBe('Total Keywords')

    await i18n.changeLanguage('ru')
    expect(i18n.t('dashboard.title')).toBe('Дашборд')
    expect(i18n.t('dashboard.totalKeywords')).toBe('Всего ключевых слов')
  })

  it('translates keywords labels in both languages', async () => {
    expect(i18n.t('keywords.keyword')).toBe('Keyword')
    expect(i18n.t('keywords.position')).toBe('Position')
    expect(i18n.t('keywords.importKeywords')).toBe('Import Keywords')

    await i18n.changeLanguage('ru')
    expect(i18n.t('keywords.keyword')).toBe('Ключевое слово')
    expect(i18n.t('keywords.position')).toBe('Позиция')
    expect(i18n.t('keywords.importKeywords')).toBe('Импорт ключевых слов')
  })

  it('translates settings theme labels', async () => {
    expect(i18n.t('settings.themeLight')).toBe('Light')
    expect(i18n.t('settings.themeDark')).toBe('Dark')

    await i18n.changeLanguage('ru')
    expect(i18n.t('settings.themeLight')).toBe('Светлая')
    expect(i18n.t('settings.themeDark')).toBe('Темная')
  })

  it('handles interpolation in page strings', () => {
    const result = i18n.t('keywords.page', { current: 2, last: 5 })
    expect(result).toBe('Page 2 of 5')
  })

  it('handles interpolation in Russian', async () => {
    await i18n.changeLanguage('ru')
    const result = i18n.t('keywords.page', { current: 2, last: 5 })
    expect(result).toBe('Страница 2 из 5')
  })

  it('switches back and forth between languages', async () => {
    expect(i18n.t('auth.login')).toBe('Log In')
    await i18n.changeLanguage('ru')
    expect(i18n.t('auth.login')).toBe('Войти')
    await i18n.changeLanguage('en')
    expect(i18n.t('auth.login')).toBe('Log In')
  })
})
