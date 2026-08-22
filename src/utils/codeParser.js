/**
 * Parse Mermaid Flowchart syntax (e.g. graph TD / A --> B) into nested Mindmap Tree.
 */
export function parseMermaidToTree(text) {
  const lines = text.split('\n').map(l => l.trim()).filter(l => l.length > 0)
  
  const nodes = {} // id -> { id, text, children: [] }
  const hasParent = new Set()
  
  // Helper to extract node ID and text: e.g. "A[Some Text]" or "A(Text)"
  const parseNodeDef = (str) => {
    str = str.trim()
    const match = str.match(/^([a-zA-Z0-9_\-]+)(?:\[(.*?)\]|\((.*?)\)|\{\{(.*?)\}\}|\("(.*?)"\))?$/)
    if (match) {
      const id = match[1]
      const text = match[2] || match[3] || match[4] || match[5] || id
      return { id, text }
    }
    return { id: str, text: str }
  }

  for (const line of lines) {
    // Skip graph definitions
    if (/^(graph|flowchart|subgraph|end)\b/i.test(line)) {
      continue
    }

    const parts = line.split(/-->|->|—>/)
    if (parts.length >= 2) {
      for (let i = 0; i < parts.length - 1; i++) {
        const parentDef = parseNodeDef(parts[i])
        const childDef = parseNodeDef(parts[i+1])

        if (!nodes[parentDef.id]) {
          nodes[parentDef.id] = { id: parentDef.id, text: parentDef.text, children: [] }
        } else if (parentDef.text !== parentDef.id) {
          nodes[parentDef.id].text = parentDef.text
        }

        if (!nodes[childDef.id]) {
          nodes[childDef.id] = { id: childDef.id, text: childDef.text, children: [] }
        } else if (childDef.text !== childDef.id) {
          nodes[childDef.id].text = childDef.text
        }

        if (!nodes[parentDef.id].children.some(c => c.id === childDef.id)) {
          nodes[parentDef.id].children.push(nodes[childDef.id])
        }
        hasParent.add(childDef.id)
      }
    } else {
      const def = parseNodeDef(line)
      if (def.id) {
        if (!nodes[def.id]) {
          nodes[def.id] = { id: def.id, text: def.text, children: [] }
        } else if (def.text !== def.id) {
          nodes[def.id].text = def.text
        }
      }
    }
  }

  const nodeKeys = Object.keys(nodes)
  if (nodeKeys.length === 0) return null

  // Find root
  let root = null
  for (const key of nodeKeys) {
    if (!hasParent.has(key)) {
      root = nodes[key]
      break
    }
  }

  if (!root) {
    root = nodes[nodeKeys[0]]
  }

  const COLORS = ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ec4899', '#06b6d4', '#f43f5e']
  
  // Recursively format to our tree schema
  const cleanAndFormat = (node, depth = 0, color = null) => {
    const formattedId = node.id === root.id ? 'root' : 'node-' + Math.random().toString(36).substr(2, 9)
    const nodeColor = depth === 0 ? '#1b1b1f' : (depth === 1 ? COLORS[Math.floor(Math.random() * COLORS.length)] : color)
    
    // De-duplicate children to prevent recursion cycles
    const seen = new Set()
    const children = []
    if (node.children) {
      for (const child of node.children) {
        if (!seen.has(child.id)) {
          seen.add(child.id)
          children.push(cleanAndFormat(child, depth + 1, nodeColor))
        }
      }
    }
    
    return {
      id: formattedId,
      text: node.text,
      color: nodeColor,
      expanded: true,
      children
    }
  }

  return cleanAndFormat(root)
}

/**
 * Clean and parse raw JSON text. Supports code block stripping.
 */
export function parseRawJson(text) {
  let cleaned = text.trim()
  // Remove markdown code block fences if present
  if (cleaned.startsWith('```')) {
    cleaned = cleaned.replace(/^```json\s*/i, '').replace(/```$/, '').trim()
  }
  
  const parsed = JSON.parse(cleaned)
  if (parsed && typeof parsed === 'object') {
    return parsed
  }
  throw new Error('Not a valid JSON object')
}
