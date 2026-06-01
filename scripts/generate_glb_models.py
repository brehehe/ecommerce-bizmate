#!/usr/bin/env python3
"""
Generate GLB 3D models for ecommerce product mockups.
No external dependencies - uses only Python stdlib (struct, json, math).
"""

import json
import math
import struct
import os

OUTPUT_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'public', 'assets')

# ──────────────────────────────────────────────────────────────────────────────
# Core GLB builder
# ──────────────────────────────────────────────────────────────────────────────

def pack_f32(values):
    return struct.pack(f'{len(values)}f', *values)

def pack_u16(values):
    return struct.pack(f'{len(values)}H', *values)

def pad4(data: bytes) -> bytes:
    r = len(data) % 4
    if r:
        data += b'\x00' * (4 - r)
    return data

def make_glb(meshes):
    """
    meshes: list of dicts with keys:
        positions  – list of (x,y,z) tuples
        normals    – list of (x,y,z) tuples
        uvs        – list of (u,v) tuples
        indices    – list of ints (triangles)
        name       – string
    Returns bytes of a valid GLB file.
    """
    bin_data = b''
    accessors = []
    buffer_views = []
    meshes_json = []
    primitives_json_list = []

    def add_buffer_view(data, target):
        nonlocal bin_data
        bv_offset = len(bin_data)
        data_padded = data  # we'll pad the whole bin at end
        bin_data += data
        bv = {
            'buffer': 0,
            'byteOffset': bv_offset,
            'byteLength': len(data),
            'target': target
        }
        buffer_views.append(bv)
        return len(buffer_views) - 1

    def add_accessor(bv_idx, component_type, count, accessor_type, min_val=None, max_val=None):
        acc = {
            'bufferView': bv_idx,
            'componentType': component_type,
            'count': count,
            'type': accessor_type,
        }
        if min_val is not None:
            acc['min'] = min_val
        if max_val is not None:
            acc['max'] = max_val
        accessors.append(acc)
        return len(accessors) - 1

    ARRAY_BUFFER = 34962
    ELEMENT_ARRAY_BUFFER = 34963
    FLOAT = 5126
    UNSIGNED_SHORT = 5123

    for mesh in meshes:
        positions = mesh['positions']
        normals   = mesh['normals']
        uvs       = mesh['uvs']
        indices   = mesh['indices']

        # Positions
        pos_bytes = pack_f32([v for p in positions for v in p])
        pos_bv = add_buffer_view(pos_bytes, ARRAY_BUFFER)
        xs = [p[0] for p in positions]; ys = [p[1] for p in positions]; zs = [p[2] for p in positions]
        pos_acc = add_accessor(pos_bv, FLOAT, len(positions), 'VEC3',
                               [min(xs), min(ys), min(zs)], [max(xs), max(ys), max(zs)])

        # Normals
        nor_bytes = pack_f32([v for n in normals for v in n])
        nor_bv = add_buffer_view(nor_bytes, ARRAY_BUFFER)
        nor_acc = add_accessor(nor_bv, FLOAT, len(normals), 'VEC3')

        # UVs
        uv_bytes = pack_f32([v for u in uvs for v in u])
        uv_bv = add_buffer_view(uv_bytes, ARRAY_BUFFER)
        uv_acc = add_accessor(uv_bv, FLOAT, len(uvs), 'VEC2')

        # Indices
        idx_bytes = pack_u16(indices)
        idx_bv = add_buffer_view(idx_bytes, ELEMENT_ARRAY_BUFFER)
        idx_acc = add_accessor(idx_bv, UNSIGNED_SHORT, len(indices), 'SCALAR',
                               [min(indices)], [max(indices)])

        mesh_prim = {
            'primitives': [{
                'attributes': {
                    'POSITION': pos_acc,
                    'NORMAL':   nor_acc,
                    'TEXCOORD_0': uv_acc,
                },
                'indices': idx_acc,
                'mode': 4,
            }],
            'name': mesh.get('name', 'Mesh'),
        }
        primitives_json_list.append(mesh_prim)

    # Pad bin_data
    bin_data = pad4(bin_data)

    gltf = {
        'asset': {'version': '2.0', 'generator': 'PythonGLBGenerator'},
        'scene': 0,
        'scenes': [{'nodes': list(range(len(meshes)))}],
        'nodes': [{'mesh': i, 'name': m.get('name', f'Node{i}')} for i, m in enumerate(meshes)],
        'meshes': primitives_json_list,
        'accessors': accessors,
        'bufferViews': buffer_views,
        'buffers': [{'byteLength': len(bin_data)}],
    }

    json_bytes = json.dumps(gltf, separators=(',', ':')).encode('utf-8')
    json_bytes = pad4(json_bytes)

    # GLB header
    total = 12 + 8 + len(json_bytes) + 8 + len(bin_data)
    header = struct.pack('<4sII', b'glTF', 2, total)
    json_chunk = struct.pack('<II', len(json_bytes), 0x4E4F534A) + json_bytes
    bin_chunk  = struct.pack('<II', len(bin_data),  0x004E4942) + bin_data

    return header + json_chunk + bin_chunk


# ──────────────────────────────────────────────────────────────────────────────
# Shape generators
# ──────────────────────────────────────────────────────────────────────────────

def make_cylinder(radius=0.5, height=1.0, segments=32, bottom_cap=True, top_cap=True):
    """Returns positions, normals, uvs, indices for a cylinder."""
    positions, normals, uvs, indices = [], [], [], []

    # Side wall
    for i in range(segments + 1):
        angle = 2 * math.pi * i / segments
        x = math.cos(angle) * radius
        z = math.sin(angle) * radius
        u = i / segments
        for y, v in [(0.0, 0.0), (height, 1.0)]:
            positions.append((x, y, z))
            normals.append((math.cos(angle), 0.0, math.sin(angle)))
            uvs.append((u, v))

    rows = segments + 1
    for i in range(segments):
        b0 = i * 2; b1 = b0 + 2; t0 = b0 + 1; t1 = b1 + 1
        indices += [b0, t0, b1, b1, t0, t1]

    def add_cap(y, normal_y, flip):
        center_idx = len(positions)
        positions.append((0, y, 0)); normals.append((0, normal_y, 0)); uvs.append((0.5, 0.5))
        ring_start = len(positions)
        for i in range(segments):
            angle = 2 * math.pi * i / segments
            x = math.cos(angle) * radius; z = math.sin(angle) * radius
            positions.append((x, y, z)); normals.append((0, normal_y, 0))
            uvs.append((0.5 + 0.5 * math.cos(angle), 0.5 + 0.5 * math.sin(angle)))
        for i in range(segments):
            a = ring_start + i; b = ring_start + (i + 1) % segments
            if flip:
                indices.extend([center_idx, b, a])
            else:
                indices.extend([center_idx, a, b])

    if bottom_cap: add_cap(0.0, -1.0, True)
    if top_cap:    add_cap(height, 1.0, False)

    return positions, normals, uvs, indices


def make_box(w=1.0, h=1.0, d=1.0, ox=0, oy=0, oz=0):
    """Returns a box mesh."""
    hw, hh, hd = w/2, h/2, d/2
    faces = [
        # pos, normal, u, v corners – each face = 2 triangles
        # +X
        ((hw,  hh,  hd), (hw, -hh,  hd), (hw, -hh, -hd), (hw,  hh, -hd), (1,0,0)),
        # -X
        ((-hw,  hh, -hd), (-hw, -hh, -hd), (-hw, -hh,  hd), (-hw,  hh,  hd), (-1,0,0)),
        # +Y
        ((-hw,  hh, -hd), (-hw,  hh,  hd), ( hw,  hh,  hd), ( hw,  hh, -hd), (0,1,0)),
        # -Y
        ((-hw, -hh,  hd), (-hw, -hh, -hd), ( hw, -hh, -hd), ( hw, -hh,  hd), (0,-1,0)),
        # +Z
        ((-hw,  hh,  hd), (-hw, -hh,  hd), ( hw, -hh,  hd), ( hw,  hh,  hd), (0,0,1)),
        # -Z
        (( hw,  hh, -hd), ( hw, -hh, -hd), (-hw, -hh, -hd), (-hw,  hh, -hd), (0,0,-1)),
    ]
    positions, normals, uvs, indices = [], [], [], []
    uv_corners = [(0,1),(0,0),(1,0),(1,1)]
    for f in faces:
        pts, n = f[:4], f[4]
        base = len(positions)
        for idx, p in enumerate(pts):
            positions.append((p[0]+ox, p[1]+oy, p[2]+oz))
            normals.append(n)
            uvs.append(uv_corners[idx])
        indices += [base, base+1, base+2, base, base+2, base+3]
    return positions, normals, uvs, indices


def merge_meshes(*mesh_tuples):
    """Merge multiple (pos, nor, uv, idx) tuples into one."""
    all_pos, all_nor, all_uv, all_idx = [], [], [], []
    for p, n, u, i in mesh_tuples:
        offset = len(all_pos)
        all_pos += p; all_nor += n; all_uv += u
        all_idx += [x + offset for x in i]
    return all_pos, all_nor, all_uv, all_idx


def make_torus(major=0.5, minor=0.12, maj_seg=24, min_seg=12):
    """Returns a torus (donut shape, useful for handles)."""
    positions, normals, uvs, indices = [], [], [], []
    for i in range(maj_seg + 1):
        theta = 2 * math.pi * i / maj_seg
        ct, st = math.cos(theta), math.sin(theta)
        for j in range(min_seg + 1):
            phi = 2 * math.pi * j / min_seg
            cp, sp = math.cos(phi), math.sin(phi)
            x = (major + minor * cp) * ct
            y = minor * sp
            z = (major + minor * cp) * st
            nx, ny, nz = cp * ct, sp, cp * st
            positions.append((x, y, z))
            normals.append((nx, ny, nz))
            uvs.append((i / maj_seg, j / min_seg))

    for i in range(maj_seg):
        for j in range(min_seg):
            a = i * (min_seg + 1) + j
            b = a + 1
            c = a + (min_seg + 1)
            d = c + 1
            indices += [a, c, b, b, c, d]

    return positions, normals, uvs, indices


def make_disc(radius=1.0, thickness=0.05, segments=48):
    """Flat disc (plate shape)."""
    top,   _, _, _ = make_cylinder(radius, thickness, segments, bottom_cap=True, top_cap=True)
    p, n, u, i = make_cylinder(radius, thickness, segments, bottom_cap=True, top_cap=True)
    return p, n, u, i


def make_dome(radius=0.5, segments=32, rings=16):
    """Half-sphere dome."""
    positions, normals, uvs, indices = [], [], [], []
    for r in range(rings + 1):
        phi = (math.pi / 2) * r / rings  # 0 to pi/2
        y = radius * math.sin(phi)
        ring_r = radius * math.cos(phi)
        for s in range(segments + 1):
            theta = 2 * math.pi * s / segments
            x = ring_r * math.cos(theta)
            z = ring_r * math.sin(theta)
            nx, ny, nz = x/radius, y/radius, z/radius
            positions.append((x, y, z))
            normals.append((nx, ny, nz))
            uvs.append((s/segments, r/rings))

    for r in range(rings):
        for s in range(segments):
            a = r * (segments+1) + s
            b = a + 1
            c = a + (segments+1)
            d = c + 1
            indices += [a, c, b, b, c, d]

    return positions, normals, uvs, indices


# ──────────────────────────────────────────────────────────────────────────────
# Individual product model builders
# ──────────────────────────────────────────────────────────────────────────────

def build_mug():
    """Mug: main cylinder body + torus handle on the side."""
    # Body: radius 0.35, height 0.85
    bp, bn, bu, bi = make_cylinder(0.35, 0.85, 32)
    # Handle: torus arc on the right side
    # Build a partial torus (semi-circle) positioned as a handle
    handle_pos, handle_nor, handle_uv, handle_idx = [], [], [], []
    minor = 0.06
    major_r = 0.25
    maj_seg = 16
    min_seg = 10
    for i in range(maj_seg + 1):
        theta = math.pi * i / maj_seg  # half circle 0..pi
        ct, st = math.cos(theta), math.sin(theta)
        for j in range(min_seg + 1):
            phi = 2 * math.pi * j / min_seg
            cp, sp = math.cos(phi), math.sin(phi)
            x = 0.35 + (major_r + minor * cp) * ct
            y = 0.425 + (major_r + minor * cp) * st - major_r
            z = minor * sp * 0.3
            nx = cp * ct; ny = cp * st; nz = sp * 0.3
            length = max(0.001, math.sqrt(nx*nx + ny*ny + nz*nz))
            handle_pos.append((x, y, z))
            handle_nor.append((nx/length, ny/length, nz/length))
            handle_uv.append((i/maj_seg, j/min_seg))

    for i in range(maj_seg):
        for j in range(min_seg):
            a = i * (min_seg+1) + j
            b = a + 1; c = a + (min_seg+1); d = c + 1
            handle_idx += [a, c, b, b, c, d]

    p, n, u, idx = merge_meshes((bp, bn, bu, bi), (handle_pos, handle_nor, handle_uv, handle_idx))
    return {'name': 'Mug', 'positions': p, 'normals': n, 'uvs': u, 'indices': idx}


def build_cap():
    """Cap: dome on top + flat brim disc."""
    # Crown: dome
    dp, dn, du, di = make_dome(0.5, 32, 16)
    # Brim: thin disc, bigger radius
    bp, bn, bu, bi = make_disc(0.75, 0.04, 40)
    # shift brim down slightly
    bp = [(x, y-0.02, z) for x,y,z in bp]
    p, n, u, idx = merge_meshes((dp, dn, du, di), (bp, bn, bu, bi))
    return {'name': 'Cap', 'positions': p, 'normals': n, 'uvs': u, 'indices': idx}


def build_totebag():
    """Totebag: rectangular bag body."""
    # Main bag body: tall box, wider than deep
    p, n, u, idx = make_box(0.7, 0.85, 0.25, 0, 0.425, 0)
    # Two handle loops (thin boxes)
    lh1_p, lh1_n, lh1_u, lh1_i = make_box(0.04, 0.3, 0.04, -0.18, 1.0, 0)
    lh2_p, lh2_n, lh2_u, lh2_i = make_box(0.04, 0.3, 0.04,  0.18, 1.0, 0)
    # Cross bars
    cb1_p, cb1_n, cb1_u, cb1_i = make_box(0.32, 0.04, 0.04, 0, 1.28, 0)
    p, n, u, idx = merge_meshes(
        (p, n, u, idx),
        (lh1_p, lh1_n, lh1_u, lh1_i),
        (lh2_p, lh2_n, lh2_u, lh2_i),
        (cb1_p, cb1_n, cb1_u, cb1_i),
    )
    return {'name': 'Totebag', 'positions': p, 'normals': n, 'uvs': u, 'indices': idx}


def build_hoodie():
    """Simplified hoodie shape: body + sleeves + hood."""
    # Body (wide box)
    bp, bn, bu, bi = make_box(1.0, 1.1, 0.35, 0, 0.55, 0)
    # Left sleeve
    lp, ln, lu, li = make_box(0.55, 0.28, 0.28, -0.775, 0.95, 0)
    # Right sleeve
    rp, rn, ru, ri = make_box(0.55, 0.28, 0.28,  0.775, 0.95, 0)
    # Hood (box on top)
    hp, hn, hu, hi = make_box(0.68, 0.5, 0.45, 0, 1.35, -0.06)
    # Neck cutout approximated by smaller box on top of body
    p, n, u, idx = merge_meshes(
        (bp, bn, bu, bi),
        (lp, ln, lu, li),
        (rp, rn, ru, ri),
        (hp, hn, hu, hi),
    )
    return {'name': 'Hoodie', 'positions': p, 'normals': n, 'uvs': u, 'indices': idx}


def build_piring():
    """Plate: wide flat disc with shallow rim."""
    # Base flat disc
    p, n, u, idx = make_cylinder(1.0, 0.08, 48, bottom_cap=True, top_cap=True)
    # Rim ring: slightly larger, thin
    rp, rn, ru, ri = make_cylinder(1.05, 0.12, 48, bottom_cap=False, top_cap=True)
    p, n, u, idx = merge_meshes((p, n, u, idx), (rp, rn, ru, ri))
    return {'name': 'Piring', 'positions': p, 'normals': n, 'uvs': u, 'indices': idx}


def build_plakat():
    """Acrylic plaque: flat rectangle + triangular stand."""
    # Main plate
    pp, pn, pu, pi = make_box(0.8, 0.6, 0.04, 0, 0.55, 0)
    # Stand base
    sb_p, sb_n, sb_u, sb_i = make_box(0.5, 0.04, 0.15, 0, 0.02, 0.06)
    # Stand leg (small triangle approximated by thin box angled)
    sl_p, sl_n, sl_u, sl_i = make_box(0.06, 0.38, 0.04, 0, 0.23, 0.05)
    p, n, u, idx = merge_meshes(
        (pp, pn, pu, pi),
        (sb_p, sb_n, sb_u, sb_i),
        (sl_p, sl_n, sl_u, sl_i),
    )
    return {'name': 'Plakat', 'positions': p, 'normals': n, 'uvs': u, 'indices': idx}


# ──────────────────────────────────────────────────────────────────────────────
# Main
# ──────────────────────────────────────────────────────────────────────────────

MODELS = {
    'mug':     build_mug,
    'cap':     build_cap,
    'totebag': build_totebag,
    'hoodie':  build_hoodie,
    'piring':  build_piring,
    'plakat':  build_plakat,
}

if __name__ == '__main__':
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    for name, builder in MODELS.items():
        mesh = builder()
        # Validate indices
        max_idx = max(mesh['indices'])
        num_verts = len(mesh['positions'])
        if max_idx >= num_verts:
            print(f'  ERROR {name}: index {max_idx} >= vertex count {num_verts}')
            continue
        glb_bytes = make_glb([mesh])
        out_path = os.path.join(OUTPUT_DIR, f'{name}.glb')
        with open(out_path, 'wb') as f:
            f.write(glb_bytes)
        size_kb = len(glb_bytes) / 1024
        print(f'  ✓ {name}.glb  ({size_kb:.1f} KB, {num_verts} vertices, {len(mesh["indices"])//3} triangles)')

    print('\nAll done! Files saved to:', OUTPUT_DIR)
