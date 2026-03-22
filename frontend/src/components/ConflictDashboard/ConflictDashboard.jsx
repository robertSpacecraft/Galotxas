import React, { useState } from 'react';
import { useConflicts } from '../../hooks/useConflicts';
import styles from './ConflictDashboard.module.css';

export const ConflictDashboard = () => {
  const { 
    conflicts, 
    selectedConflictData, 
    loadingList, 
    loadingDetail, 
    actionLoading, 
    error, 
    fetchConflictDetail,
    resolveConflict
  } = useConflicts();

  const [resolveForm, setResolveForm] = useState({ home_score: '', away_score: '' });

  const handleSelect = (matchId) => {
    fetchConflictDetail(matchId);
    setResolveForm({ home_score: '', away_score: '' }); // reset form
  };

  const handleChange = (e) => {
    setResolveForm({ ...resolveForm, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!selectedConflictData) return;
    
    await resolveConflict(selectedConflictData.matchId, {
      home_score: parseInt(resolveForm.home_score, 10),
      away_score: parseInt(resolveForm.away_score, 10),
    });
  };

  return (
    <div className={styles.container}>
      <aside className={styles.sidebar}>
        <h2 className={styles.title}>Partidos en Conflicto</h2>
        
        {error && <div className={styles.error}>{error}</div>}
        {loadingList && <p>Cargando conflictos...</p>}
        
        {!loadingList && conflicts.length === 0 && (
          <p style={{color: '#6b7280'}}>No hay conflictos en revisión.</p>
        )}

        <div className={styles.conflictList}>
          {conflicts.map(match => (
            <div 
              key={match.id} 
              className={`${styles.conflictItem} ${selectedConflictData?.matchId === match.id ? styles.conflictItemActive : ''}`}
              onClick={() => handleSelect(match.id)}
            >
              <div className={styles.teamRow}>
                <span>Local</span>
                <strong>{match.home_team?.name || 'Local'}</strong>
              </div>
              <div className={styles.teamRow}>
                <span>Visitante</span>
                <strong>{match.away_team?.name || 'Visitante'}</strong>
              </div>
              <div style={{fontSize: '0.75rem', color: '#9ca3af', marginTop: '0.5rem'}}>
                ID: {match.id} • {new Date(match.scheduled_at).toLocaleDateString()}
              </div>
            </div>
          ))}
        </div>
      </aside>

      <main className={styles.mainPanel}>
        {!selectedConflictData && (
          <div className={styles.noSelection}>
            Selecciona un partido de la lista para resolver su conflicto.
          </div>
        )}

        {loadingDetail && <p>Cargando detalle del conflicto...</p>}

        {selectedConflictData && !loadingDetail && (
          <>
            <h2 className={styles.title}>Detalles del Conflicto</h2>
            
            {/* Display the comparison. The 'details' object typically contains reports. */}
            {selectedConflictData.details && selectedConflictData.details.reports ? (
              <div className={styles.reportsComparison}>
                {selectedConflictData.details.reports.map((report, idx) => (
                  <div key={idx} className={styles.reportCard}>
                    <h3 className={styles.reportTitle}>
                      Reporte de {report.team?.name || 'Equipo ' + (idx + 1)}
                    </h3>
                    <div className={styles.metric}>
                      <span className={styles.metricLabel}>Pts. Local</span>
                      <span className={styles.metricValue}>{report.home_score}</span>
                    </div>
                    <div className={styles.metric}>
                      <span className={styles.metricLabel}>Pts. Visitante</span>
                      <span className={styles.metricValue}>{report.away_score}</span>
                    </div>
                    {report.comment && (
                       <div style={{marginTop: '1rem', fontSize: '0.875rem', fontStyle: 'italic', color: '#475569'}}>
                         "{report.comment}"
                       </div>
                    )}
                  </div>
                ))}
              </div>
            ) : (
               <div style={{marginBottom: '2rem'}}>
                 No se pudieron obtener los reportes detallados, o la estructura de datos es diferente.
                 Revisa la red para debugging.
               </div>
            )}

            <form onSubmit={handleSubmit} className={styles.resolutionForm}>
              <h3 className={styles.formTitle}>Dictar Resultado Final (Admin)</h3>
              
              <div className={styles.inputGroup}>
                <label className={styles.label}>Puntuación Final Local</label>
                <input 
                  type="number" 
                  name="home_score"
                  required
                  min="0"
                  className={styles.input}
                  value={resolveForm.home_score}
                  onChange={handleChange}
                />
              </div>

              <div className={styles.inputGroup}>
                <label className={styles.label}>Puntuación Final Visitante</label>
                <input 
                  type="number" 
                  name="away_score"
                  required
                  min="0"
                  className={styles.input}
                  value={resolveForm.away_score}
                  onChange={handleChange}
                />
              </div>

              <button 
                type="submit" 
                className={styles.btnResolve}
                disabled={actionLoading}
              >
                {actionLoading ? 'Solucionando...' : 'Resolver Conflicto y Validar'}
              </button>
            </form>
          </>
        )}
      </main>
    </div>
  );
};
