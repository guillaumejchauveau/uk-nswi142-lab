class Tile {
  _el
  _pos_x
  _pos_y

  constructor (el) {
    if (!(el instanceof HTMLElement)) {
      throw new Error('Invalid argument')
    }
    this._el = el
    this.setPos(0, 0)
  }

  setPos (x, y) {
    this._pos_x = x
    this._pos_y = y

    this._el.style.left = x * 20 + 'vmin'
    this._el.style.top = y * 20 + 'vmin'
  }

  getX () {
    return this._pos_x
  }

  getY () {
    return this._pos_y
  }
}

class Game {
  _height
  _width
  _tiles
  _empty_tile_x
  _empty_tile_y

  /**
   *
   * @param board Element
   * @param width Integer
   * @param height Integer
   */
  constructor (board, width, height) {
    if (!(board instanceof Element)) {
      throw new Error('Invalid argument')
    }
    this._width = width
    this._height = height
    this._tiles = []
    for (let child of board.getElementsByClassName('game-tile')) {
      let tile = new Tile(child)
      child.addEventListener('click', e => {
        this.moveTile(tile)
      })
      this._tiles.push(tile)
    }
    if (this._tiles.length !== width * height - 1) {
      throw new Error('Invalid board')
    }

    for (let i = 0; i < 15; i++) {
      this._tiles[i].setPos(i % width, Math.floor(i / width))
    }
    this._empty_tile_x = 3
    this._empty_tile_y = 3

    for (let n = 0; n < 1000; n++) {
      this._moveTile(this._tiles[Math.floor(Math.random() * this._tiles.length)])
    }
  }

  isFinished () {
    for (let i = 0; i < this._tiles.length; i++) {
      if (this._tiles[i].getX() !== i % this._width || this._tiles[i].getY() !== Math.floor(i / this._width)) {
        return false
      }
    }
    return true
  }

  _moveTile (tile) {
    if (!this._tiles.indexOf(tile) < 0) {
      throw new Error('Invalid argument')
    }

    let xOffset = this._empty_tile_x - tile.getX()
    let yOffset = this._empty_tile_y - tile.getY()
    if (
      (Math.abs(xOffset) <= 1 && Math.abs(yOffset) <= 1) &&
      !(Math.abs(xOffset) === 1 && Math.abs(yOffset) === 1)
    ) {
      tile.setPos(this._empty_tile_x, this._empty_tile_y)
      this._empty_tile_x -= xOffset
      this._empty_tile_y -= yOffset
    }
  }

  moveTile (tile) {
    this._moveTile(tile)

    if (this.isFinished()) {
      window.alert('Finished')
    }
  }
}

document.addEventListener('DOMContentLoaded', e => {
  let a = new Game(document.getElementById('game-board'), 4, 4)
  console.log(a)
})
