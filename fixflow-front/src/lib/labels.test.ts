import { describe, expect, it } from 'vitest'
import { describeChange, formatSize, valueLabel } from './labels'

describe('labels', () => {
  it('traduit les valeurs techniques', () => {
    expect(valueLabel('status', 'in_progress')).toBe('En cours')
    expect(valueLabel('priority', 'critical')).toBe('Critique')
    expect(valueLabel('assignee', null)).toBe('—')
    expect(valueLabel('assignee', 'Dev Demo')).toBe('Dev Demo')
  })

  it("décrit une ligne d'historique", () => {
    expect(describeChange({ field: 'created', oldValue: null, newValue: 'FF-1' })).toBe("a créé l'incident")
    expect(describeChange({ field: 'status', oldValue: 'new', newValue: 'resolved' })).toBe('a modifié le statut : Nouveau → Résolu')
    expect(describeChange({ field: 'description', oldValue: 'a', newValue: 'b' })).toBe('a modifié la description')
  })

  it('formate les tailles de fichier', () => {
    expect(formatSize(512)).toBe('512 o')
    expect(formatSize(2048)).toBe('2.0 Ko')
    expect(formatSize(5 * 1024 * 1024)).toBe('5.0 Mo')
  })
})
