import { Link } from 'react-router-dom';
import styles from './KnowledgeRenderer.module.css';

const renderInlineNodes = (nodes, keyPrefix) => {
  if (!Array.isArray(nodes)) return null;

  return nodes.map((node, index) => {
    const key = `${keyPrefix}-${index}`;

    switch (node?.type) {
      case 'text':
        return node.value;
      case 'strong':
        return <strong key={key}>{renderInlineNodes(node.children, key)}</strong>;
      case 'emphasis':
        return <em key={key}>{renderInlineNodes(node.children, key)}</em>;
      case 'reference':
        return <Link key={key} to={node.href}>{node.label}</Link>;
      default:
        return null;
    }
  });
};

const KnowledgeHeading = ({ block, blockKey }) => {
  if (!Number.isInteger(block.level) || block.level < 2 || block.level > 6) {
    return null;
  }

  const Heading = `h${block.level}`;
  return (
    <Heading id={block.id}>
      {renderInlineNodes(block.children, `${blockKey}-inline`)}
    </Heading>
  );
};

const KnowledgeList = ({ block, ordered, blockKey }) => {
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

const KnowledgeTable = ({ block, blockKey }) => (
  <div
    className={styles.tableContainer}
    role="region"
    aria-label="Tabla con desplazamiento horizontal"
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
  const blockKey = `knowledge-block-${index}`;

  switch (block?.type) {
    case 'paragraph':
      return <p key={blockKey}>{renderInlineNodes(block.children, blockKey)}</p>;
    case 'heading':
      return <KnowledgeHeading key={blockKey} block={block} blockKey={blockKey} />;
    case 'unorderedList':
      return <KnowledgeList key={blockKey} block={block} blockKey={blockKey} ordered={false} />;
    case 'orderedList':
      return <KnowledgeList key={blockKey} block={block} blockKey={blockKey} ordered />;
    case 'table':
      return <KnowledgeTable key={blockKey} block={block} blockKey={blockKey} />;
    case 'thematicBreak':
      return <hr key={blockKey} />;
    default:
      return null;
  }
};

export const KnowledgeRenderer = ({ blocks }) => (
  <div className={styles.content}>
    {Array.isArray(blocks) ? blocks.map(renderBlock) : null}
  </div>
);
