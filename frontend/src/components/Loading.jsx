export function Loading({ label = 'Caricamento', rows = 3 }) {
  return (
    <div className="loading" aria-live="polite">
      <span>{label}</span>
      <div className="skeleton-stack" aria-hidden="true">
        {Array.from({ length: rows }).map((_, index) => (
          <div className="skeleton-card" key={index}>
            <span className="skeleton-line wide" />
            <span className="skeleton-line" />
          </div>
        ))}
      </div>
    </div>
  );
}
