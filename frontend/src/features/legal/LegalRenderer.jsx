import styles from './LegalPage.module.css';

const renderInlineNodes = (nodes, keyPrefix) => (
  Array.isArray(nodes) ? nodes.map((node, index) => {
    const key = `${keyPrefix}-${index}`;

    switch (node?.type) {
      case 'text':
        return node.value;
      case 'strong':
        return <strong key={key}>{renderInlineNodes(node.children, key)}</strong>;
      case 'emphasis':
        return <em key={key}>{renderInlineNodes(node.children, key)}</em>;
      case 'inlineCode':
        return <code key={key}>{node.value}</code>;
      case 'externalLink':
        return (
          <a key={key} href={node.href} target="_blank" rel="noopener noreferrer">
            {node.label}
            <span className={styles.visuallyHidden}> (se abre en una pestaña nueva)</span>
          </a>
        );
      default:
        return null;
    }
  }) : null
);

const LegalHeading = ({ block, blockKey }) => {
  if (!Number.isInteger(block.level) || block.level < 2 || block.level > 6) return null;
  const Heading = `h${block.level}`;
  return (
    <Heading id={block.id} tabIndex={-1}>
      {renderInlineNodes(block.children, `${blockKey}-inline`)}
    </Heading>
  );
};

const LegalList = ({ block, ordered, blockKey }) => {
  const List = ordered ? 'ol' : 'ul';
  return (
    <List start={ordered ? block.start : undefined}>
      {block.items.map((item, index) => (
        <li key={`${blockKey}-item-${index}`} value={ordered ? item.value : undefined}>
          {renderInlineNodes(item.children, `${blockKey}-item-${index}`)}
        </li>
      ))}
    </List>
  );
};

const LegalTable = ({ block, blockKey }) => (
  <div
    className={styles.tableContainer}
    role="region"
    aria-label="Tabla legal con desplazamiento horizontal"
    tabIndex={0}
  >
    <table>
      <thead>
        <tr>
          {block.headers.map((cell, index) => (
            <th key={`${blockKey}-header-${index}`} scope="col">
              {renderInlineNodes(cell, `${blockKey}-header-${index}`)}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {block.rows.map((row, rowIndex) => (
          <tr key={`${blockKey}-row-${rowIndex}`}>
            {row.map((cell, cellIndex) => (
              <td key={`${blockKey}-cell-${rowIndex}-${cellIndex}`}>
                {renderInlineNodes(cell, `${blockKey}-cell-${rowIndex}-${cellIndex}`)}
              </td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  </div>
);

const renderBlock = (block, index) => {
  const blockKey = `legal-block-${index}`;
  switch (block?.type) {
    case 'paragraph':
      return <p key={blockKey}>{renderInlineNodes(block.children, blockKey)}</p>;
    case 'heading':
      return <LegalHeading key={blockKey} block={block} blockKey={blockKey} />;
    case 'unorderedList':
      return <LegalList key={blockKey} block={block} blockKey={blockKey} ordered={false} />;
    case 'orderedList':
      return <LegalList key={blockKey} block={block} blockKey={blockKey} ordered />;
    case 'table':
      return <LegalTable key={blockKey} block={block} blockKey={blockKey} />;
    case 'thematicBreak':
      return <hr key={blockKey} />;
    default:
      return null;
  }
};

export const LegalRenderer = ({ blocks }) => (
  <div className={styles.body}>
    {Array.isArray(blocks) ? blocks.map(renderBlock) : null}
  </div>
);
