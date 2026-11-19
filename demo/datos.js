// Demo: datos ficticios guardados solo en este navegador (localStorage). No hay base de datos.
// Lo usan la invitación demo (demo/invitacion) y el panel demo (demo/admin).
(() => {
  const KEY = 'demo-invitacion-v1';
  const hoy = (d, h) => { const t = new Date(); t.setDate(t.getDate() - d); t.setHours(h, 15, 0, 0); return t.toISOString(); };
  const semilla = () => {
    const g = [
      ['Familia Pérez', 'PEREZ2027A', null, [['Ana Pérez'], ['Martín Pérez'], ['Juli Pérez', 'nino']]],
      ['Familia Gómez', 'GOMEZ2027B', hoy(6, 18), [['Laura Gómez', 'adulto', 'si', null, 'adulto'], ['Pedro Gómez', 'adulto', 'si', 'Sin TACC', 'adulto'], ['Tomi Gómez', 'nino', 'si', null, 'infantil']]],
      ['Lucía y Tomás', 'LUCIA2027C', hoy(4, 10), [['Lucía Fernández', 'adulto', 'si', 'Vegetariano', 'adulto'], ['Tomás Ríos', 'adulto', 'si', null, 'adulto']]],
      ['Familia Rodríguez', 'RODRI2027D', null, [['Carla Rodríguez'], ['Diego Rodríguez'], ['Mía Rodríguez', 'nino']]],
      ['Valentina Díaz', 'VALEN2027E', hoy(3, 21), [['Valentina Díaz', 'adulto', 'no']]],
      ['Abuelos Martínez', 'ABUEL2027F', hoy(9, 12), [['Elsa Martínez', 'adulto', 'si', null, 'adulto'], ['Rubén Martínez', 'adulto', 'si', null, 'adulto']]],
      ['Nico y Flor', 'NICOF2027G', hoy(1, 23), [['Nicolás Suárez', 'adulto', 'si', null, 'adulto'], ['Florencia Paz', 'adulto', 'no']]],
      ['Tíos Benítez', 'TIOSB2027H', null, [['Graciela Benítez'], ['Jorge Benítez']]],
    ];
    const d = { grupos: [], personas: [], confirmaciones: [], acompanantes: [], canciones: [], mensajes: [], fotos: [], seq: 100 };
    g.forEach(([nombre, token, respondido, ps], i) => {
      d.grupos.push({ id: i + 1, nombre, token, telefono: respondido ? '11 5' + (300 + i * 37) + '-' + (4100 + i * 11) : '', comentarios: i === 1 ? 'Pedro es celíaco 🙏' : '', respondido });
      ps.forEach(([n, tipo = 'adulto', asiste = null, menu = null, menu_tipo = null]) =>
        d.personas.push({ id: ++d.seq, grupo_id: i + 1, nombre: n, tipo, asiste, menu, menu_tipo }));
    });
    d.confirmaciones.push({ id: 1, nombre: 'Juan Ignacio López', asistencia: 'si', personas: 2, telefono: '11 4455-2211', menu: '', comentarios: '', creado: hoy(2, 16) });
    d.acompanantes.push({ id: 1, confirmacion_id: 1, nombre: 'Camila Sosa', tipo: 'adulto', restriccion: 'Vegano' });
    d.canciones.push(
      { nombre: 'Laura Gómez', cancion: 'Perfect', artista: 'Ed Sheeran', creado: hoy(6, 18) },
      { nombre: 'Tomás Ríos', cancion: 'Vivir mi vida', artista: 'Marc Anthony', creado: hoy(4, 10) },
      { nombre: 'Nicolás Suárez', cancion: 'September', artista: 'Earth, Wind & Fire', creado: hoy(1, 23) });
    d.mensajes.push(
      { nombre: 'Abuelos Martínez', mensaje: '¡Que sean muy felices! Los queremos mucho.', creado: hoy(9, 12) },
      { nombre: 'Laura Gómez', mensaje: 'Felicitaciones, chicos. ¡Nos vemos en la pista! 💃', creado: hoy(6, 18) });
    return d;
  };
  const cargar = () => { try { const d = JSON.parse(localStorage.getItem(KEY)); if (d && d.grupos) return d; } catch {} return semilla(); };
  window.DEMO = {
    datos: cargar(),
    guardar() { try { localStorage.setItem(KEY, JSON.stringify(this.datos)); } catch {} },
    reiniciar() { this.datos = semilla(); this.guardar(); },
  };
})();
