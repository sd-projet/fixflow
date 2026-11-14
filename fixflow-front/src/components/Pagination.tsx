interface Props { page: number; total: number; perPage: number; onChange: (page: number) => void }

export default function Pagination({ page, total, perPage, onChange }: Props) {
  const pages = Math.max(1, Math.ceil(total / perPage))
  return (
    <div className="row" style={{ marginTop: 12 }}>
      <button className="secondary" disabled={page <= 1} onClick={() => onChange(page - 1)}>← Précédent</button>
      <span className="muted">Page {page} / {pages} · {total} résultat{total > 1 ? 's' : ''}</span>
      <button className="secondary" disabled={page >= pages} onClick={() => onChange(page + 1)}>Suivant →</button>
    </div>
  )
}
