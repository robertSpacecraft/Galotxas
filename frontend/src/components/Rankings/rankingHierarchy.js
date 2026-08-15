export const findById = (rows, id) => (
  rows.find((row) => String(row.id) === String(id)) ?? null
);

export const firstId = (rows) => (
  rows[0]?.id === null || rows[0]?.id === undefined ? '' : String(rows[0].id)
);

export const keepValidId = (rows, currentId) => (
  findById(rows, currentId) ? String(currentId) : firstId(rows)
);
